<?php

namespace App\Http\Controllers;

use App\Actions\Auth\IssueDeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NativeSocialAuthController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:google,github'],
            'challenge' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $provider = $data['provider'];
        abort_unless(filled(config("services.{$provider}.client_id")) && filled(config("services.{$provider}.client_secret")), 503);
        $code = Str::random(64);
        Cache::put('native-auth:'.hash('sha256', $code), $data + ['expires_at' => now()->addMinutes(5)->timestamp, 'user_id' => null], now()->addMinutes(5));

        return response()->json(['code' => $code, 'url' => url("/auth/{$provider}/redirect").'?'.http_build_query(['native' => $code])], 201);
    }

    public function complete(Request $request): RedirectResponse
    {
        $code = $request->session()->get('native.code');
        abort_unless(is_string($code) && $request->user()?->id === $request->session()->get('native.user_id'), 403);
        $key = 'native-auth:'.hash('sha256', $code);
        Cache::lock($key.':lock', 10)->block(3, function () use ($key, $request): void {
            $intent = Cache::get($key);
            abort_unless(is_array($intent) && $intent['expires_at'] > now()->timestamp, 410);
            $intent['user_id'] = $request->user()->id;
            Cache::put($key, $intent, now()->addSeconds($intent['expires_at'] - now()->timestamp));
        });
        $request->session()->forget(['native.code', 'native.user_id']);

        return redirect(config('auth.mobile_return_url').'?'.http_build_query(['code' => $code]));
    }

    public function exchange(Request $request, IssueDeviceToken $issue): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:64'], 'verifier' => ['required', 'string', 'size:64']]);
        $key = 'native-auth:'.hash('sha256', $data['code']);

        return Cache::lock($key.':lock', 10)->block(3, function () use ($key, $data, $issue): JsonResponse {
            $intent = Cache::get($key);
            abort_unless(is_array($intent) && $intent['expires_at'] > now()->timestamp && $intent['user_id'] !== null && hash_equals($intent['challenge'], hash('sha256', $data['verifier'])), 422);
            Cache::forget($key);
            $user = User::query()->findOrFail($intent['user_id']);

            return response()->json($issue($user, $intent['device_name']), 201);
        });
    }
}
