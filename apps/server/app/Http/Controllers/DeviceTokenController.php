<?php

namespace App\Http\Controllers;

use App\Actions\Auth\IssueDeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class DeviceTokenController extends Controller
{
    public function store(Request $request, IssueDeviceToken $issue): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:100'],
        ]);

        return DB::transaction(function () use ($data, $issue): JsonResponse {
            $user = User::query()->where('email', strtolower($data['email']))->lockForUpdate()->first();
            if (! $user || ! Hash::check($data['password'], $user->password ?? '')) {
                throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
            }
            if ($user->hasEnabledTwoFactorAuthentication()) {
                if (empty($data['code']) && empty($data['recovery_code'])) {
                    return response()->json(['two_factor' => true], 202);
                }
                $recovery = collect(filled($user->two_factor_recovery_codes) ? $user->recoveryCodes() : [])->first(fn (string $code): bool => filled($data['recovery_code'] ?? null) && hash_equals($code, $data['recovery_code']));
                if ($recovery) {
                    $user->replaceRecoveryCode($recovery);
                } elseif (! app(TwoFactorAuthenticationProvider::class)->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $data['code'] ?? '')) {
                    throw ValidationException::withMessages(['code' => 'The two-factor code is invalid.']);
                }
            }

            return response()->json($issue($user, $data['device_name']), 201);
        });
    }

    public function destroy(Request $request): JsonResponse
    {
        abort_if(Auth::guard('web')->check(), 403, 'This endpoint requires a device token.');
        $request->user()->currentAccessToken()->delete();

        return response()->json([], 200);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->tokens()->get(['id', 'name', 'created_at', 'last_used_at', 'expires_at']));
    }

    public function revoke(Request $request, int $token): JsonResponse
    {
        $request->user()->tokens()->findOrFail($token)->delete();

        return response()->json([], 200);
    }
}
