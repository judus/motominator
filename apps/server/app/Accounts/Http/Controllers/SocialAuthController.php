<?php

namespace App\Accounts\Http\Controllers;

use App\Accounts\Actions\AuthenticateSocialAccount;
use App\Accounts\Actions\CompleteNativeSocialLink;
use App\Accounts\Data\NativeLinkIntent;
use App\Accounts\Exceptions\AccountsNativeAuthException;
use App\Accounts\Actions\EnsureSocialProvider;
use App\Accounts\Actions\LinkSocialIdentity;
use App\Accounts\Actions\UnlinkSocialIdentity;
use App\Accounts\Data\NativeAuthIntent;
use App\Accounts\Exceptions\AccountsSocialException;
use App\Http\Controllers\Controller;
use App\Support\Input;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SocialAuthController extends Controller
{
    public function __construct(private readonly EnsureSocialProvider $ensureSocialProvider)
    {
    }

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        ($this->ensureSocialProvider)($provider);
        $request->session()->forget(['native.code', 'native.user_id', 'native.security_fingerprint', 'native.link']);
        if ($request->filled('native_link')) {
            $code = $request->query('native_link');
            abort_unless(is_string($code) && strlen($code) === 64, 422);
            $native = NativeLinkIntent::fromCache(Cache::get('native-link:' . hash('sha256', $code)));
            abort_unless($native !== null && $native->providerUserId === null && $native->provider === $provider
                && $native->expiresAt > now()->timestamp, 422);
            $request->session()->put('native.link', $code);
            $request->session()->put('social.intent', ['provider' => $provider, 'type' => 'native-link']);

            return Socialite::driver($provider)->redirect();
        }
        if ($request->filled('native')) {
            $code = $request->query('native');
            abort_unless(is_string($code) && strlen($code) === 64, 422);
            $native = NativeAuthIntent::fromCache(Cache::get('native-auth:' . hash('sha256', $code)));
            abort_unless(
                $native !== null && $native->provider === $provider && $native->expiresAt > now()->timestamp
                    && $request->query('intent', 'login') === 'login',
                422,
            );
            $request->session()->put('native.code', $code);
        }
        $intent = $request->query('intent', 'login');
        abort_unless(in_array($intent, ['login', 'link'], true), 422);
        if ($intent === 'link') {
            $confirmedAt = Input::integer($request->session()->get('auth.password_confirmed_at', 0), 'confirmation');
            abort_unless(
                $request->user() !== null && now()->getTimestamp() - $confirmedAt < Config::integer(
                    'auth.password_timeout'
                ),
                403,
            );
        } else {
            abort_if($request->user() !== null, 409);
        }
        $request->session()->put(
            'social.intent',
            ['provider' => $provider, 'type' => $intent, 'user_id' => $request->user()?->id]
        );

        return Socialite::driver($provider)->redirect();
    }

    public function callback(
        Request $request,
        string $provider,
        AuthenticateSocialAccount $authenticate,
        LinkSocialIdentity $link,
        CompleteNativeSocialLink $completeLink,
    ): RedirectResponse {
        ($this->ensureSocialProvider)($provider);
        $intent = $request->session()->pull('social.intent');
        if (! is_array($intent) || ($intent['provider'] ?? null) !== $provider || $request->has('error')) {
            return $this->failure();
        }
        try {
            $external = Socialite::driver($provider)->user();
        } catch (InvalidStateException | GuzzleException $exception) {
            return $this->failure();
        }
        if (blank($external->getId())) {
            return $this->failure();
        }
        if (($intent['type'] ?? null) === 'native-link') {
            $code = $request->session()->pull('native.link');
            if (! is_string($code)) {
                return $this->failure();
            }
            try {
                $completeLink($code, $provider, $external->getId());
            } catch (AccountsNativeAuthException | AccountsSocialException | AuthorizationException $exception) {
                return redirect(Config::string('auth.mobile_return_url') . '?error=social');
            }

            return redirect(Config::string('auth.mobile_return_url') . '?' . http_build_query(['link_code' => $code]));
        }
        try {
            if (($intent['type'] ?? null) === 'link') {
                $actor = $request->user();
                if (! $actor || $actor->id !== ($intent['user_id'] ?? null)) {
                    return $this->failure();
                }
                $user = $link(
                    $actor,
                    $provider,
                    $external->getId(),
                    Input::integer($request->session()->get('auth.password_confirmed_at', 0), 'confirmation'),
                );
            } elseif (($intent['type'] ?? null) === 'login' && $request->user() === null) {
                $user = $authenticate($provider, $external);
            } else {
                return $this->failure();
            }
        } catch (AccountsSocialException | AuthorizationException $exception) {
            return $this->failure();
        }
        if (! $user) {
            return $this->failure();
        }
        if ($intent['type'] === 'link') {
            return redirect(rtrim(Config::string('fortify.frontend_url'), '/') . '/account');
        }
        $request->session()->regenerate();
        if ($request->session()->has('native.code')) {
            $request->session()->put('native.user_id', $user->id);
            $request->session()->put(
                'native.security_fingerprint',
                $user->securityFingerprint(includeRecoveryCodes: false)
            );
        }
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.remember', false);

            $query = ['two_factor' => 1];
            if ($request->session()->has('native.code')) {
                $query['native'] = 1;
            }

            return redirect(rtrim(Config::string('fortify.frontend_url'), '/') . '/login?' . http_build_query($query));
        }
        Auth::guard('web')->login($user);

        if ($request->session()->has('native.code')) {
            return redirect()->route('auth.mobile.complete');
        }

        return redirect(rtrim(Config::string('fortify.frontend_url'), '/') . '/account');
    }

    public function destroy(Request $request, string $provider, UnlinkSocialIdentity $unlink): JsonResponse
    {
        $unlink(
            $this->authenticatedUser($request),
            $provider,
            Input::integer($request->session()->get('auth.password_confirmed_at', 0), 'confirmation'),
        );

        return response()->json([], 200);
    }

    private function failure(): RedirectResponse
    {
        if (session()->has('native.code') || session()->has('native.link')) {
            session()->forget(['native.code', 'native.user_id', 'native.security_fingerprint', 'native.link']);

            return redirect(Config::string('auth.mobile_return_url') . '?error=social');
        }

        return redirect(rtrim(Config::string('fortify.frontend_url'), '/') . '/login?error=social');
    }
}
