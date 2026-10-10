<?php

namespace App\Accounts\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRecoveryRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->is('forgot-password', 'reset-password', 'register')) {
            $key = 'account-recovery:' . $request->path() . '|' . $request->ip();

            if (RateLimiter::tooManyAttempts($key, 6)) {
                return response()->json(['message' => 'Too many requests. Please try again later.'], 429);
            }

            RateLimiter::hit($key, 60);
        }

        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('DELETE')) {
            $passwordCheck = $request->is('user/password', 'user/confirm-password')
                || ($request->is('user/profile-information') && $request->filled('current_password'));
            if ($passwordCheck) {
                $actor = $request->user();
                $limits = [
                    'account-password-actor:' . ($actor instanceof User ? $actor->id : 'guest') => 10,
                    'account-password-ip:' . $request->ip() => 60,
                ];
                foreach ($limits as $key => $limit) {
                    if (RateLimiter::tooManyAttempts($key, $limit)) {
                        return response()->json(['message' => 'Too many requests. Please try again later.'], 429);
                    }
                }
                foreach ($limits as $key => $limit) {
                    RateLimiter::hit($key, 60);
                }
            }
        }

        return $next($request);
    }
}
