<?php

namespace App\Http\Middleware;

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
            $key = 'account-recovery:'.$request->path().'|'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, 6)) {
                return response()->json(['message' => 'Too many requests. Please try again later.'], 429);
            }

            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }
}
