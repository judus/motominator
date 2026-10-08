<?php

namespace App\Http\Controllers;

use App\Models\SocialIdentity;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SocialAuthController extends Controller
{
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProvider($provider);
        $request->session()->forget(['native.code', 'native.user_id']);
        if ($request->filled('native')) {
            $code = $request->query('native');
            abort_unless(is_string($code) && strlen($code) === 64, 422);
            $native = Cache::get('native-auth:'.hash('sha256', $code));
            abort_unless(is_array($native) && $native['provider'] === $provider && $native['expires_at'] > now()->timestamp && $request->query('intent', 'login') === 'login', 422);
            $request->session()->put('native.code', $code);
        }
        $intent = $request->query('intent', 'login');
        abort_unless(in_array($intent, ['login', 'link'], true), 422);
        if ($intent === 'link') {
            abort_unless($request->user() && time() - (int) $request->session()->get('auth.password_confirmed_at', 0) < config('auth.password_timeout'), 403);
        } else {
            abort_if($request->user() !== null, 409);
        }
        $request->session()->put('social.intent', ['provider' => $provider, 'type' => $intent, 'user_id' => $request->user()?->id]);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProvider($provider);
        $intent = $request->session()->pull('social.intent');
        if (! is_array($intent) || ($intent['provider'] ?? null) !== $provider || $request->has('error')) {
            return $this->failure();
        }
        try {
            $external = Socialite::driver($provider)->user();
        } catch (InvalidStateException|GuzzleException $exception) {
            return $this->failure();
        }
        if (blank($external->getId())) {
            return $this->failure();
        }
        try {
            $user = DB::transaction(function () use ($request, $provider, $external, $intent): ?User {
                $identity = SocialIdentity::query()->where('provider', $provider)->where('provider_user_id', (string) $external->getId())->first();
                if ($intent['type'] === 'link') {
                    $user = $request->user();
                    if (! $user || $user->id !== $intent['user_id'] || time() - (int) $request->session()->get('auth.password_confirmed_at', 0) >= config('auth.password_timeout')) {
                        return null;
                    }
                    $user = User::query()->lockForUpdate()->findOrFail($user->id);
                    if ($identity && $identity->user_id !== $user->id) {
                        return null;
                    }
                    if (! $identity) {
                        $user->socialIdentities()->create(['provider' => $provider, 'provider_user_id' => (string) $external->getId()]);
                    }

                    return $user;
                }
                if ($request->user()) {
                    return null;
                }
                if ($identity) {
                    return $identity->user;
                }
                $email = Str::lower((string) $external->getEmail());
                $verified = $provider === 'github' || ($external->user['email_verified'] ?? false) === true;
                if (! config('auth.registration_enabled') || ! $verified || ! filter_var($email, FILTER_VALIDATE_EMAIL) || User::query()->where('email', $email)->exists()) {
                    return null;
                }
                $user = User::create(['name' => Str::limit($external->getName() ?: $external->getNickname() ?: 'Rider', 255, ''), 'email' => $email, 'password' => null]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->socialIdentities()->create(['provider' => $provider, 'provider_user_id' => (string) $external->getId()]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->failure();
        }
        if (! $user) {
            return $this->failure();
        }
        if ($intent['type'] === 'link') {
            return redirect(rtrim(config('fortify.frontend_url'), '/').'/account');
        }
        $request->session()->regenerate();
        if ($request->session()->has('native.code')) {
            $request->session()->put('native.user_id', $user->id);
        }
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.remember', false);

            return redirect(rtrim(config('fortify.frontend_url'), '/').'/login?two_factor=1'.($request->session()->has('native.code') ? '&native=1' : ''));
        }
        Auth::guard('web')->login($user);

        if ($request->session()->has('native.code')) {
            return redirect()->route('auth.mobile.complete');
        }

        return redirect(rtrim(config('fortify.frontend_url'), '/').'/account');
    }

    public function destroy(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['google', 'github'], true), 404);
        DB::transaction(function () use ($request, $provider): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_if(blank($user->password) && $user->socialIdentities()->count() <= 1, 409, 'Set a password or link another provider before removing your final sign-in method.');
            $user->socialIdentities()->where('provider', $provider)->delete();
        });

        return response()->json([], 200);
    }

    private function ensureProvider(string $provider): void
    {
        abort_unless(in_array($provider, ['google', 'github'], true), 404);
        abort_unless(filled(config("services.{$provider}.client_id")) && filled(config("services.{$provider}.client_secret")), 503, 'This sign-in provider is not configured.');
    }

    private function failure(): RedirectResponse
    {
        if (session()->has('native.code')) {
            session()->forget(['native.code', 'native.user_id']);

            return redirect(config('auth.mobile_return_url').'?error=social');
        }

        return redirect(rtrim(config('fortify.frontend_url'), '/').'/login?error=social');
    }
}
