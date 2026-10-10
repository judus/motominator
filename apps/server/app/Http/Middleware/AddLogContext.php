<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddLogContext
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        Context::forget(['trace_id', 'activity_source']);
        $traceId = (string) Str::uuid();
        Context::add('trace_id', $traceId);
        $request->attributes->set('trace_id', $traceId);
        $started = hrtime(true);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $traceId);
        Log::info('http.request_completed', [
            'method' => $request->method(),
            'route' => $request->route()?->uri(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        Context::forget(['trace_id', 'activity_source']);
        $request->attributes->remove('trace_id');
    }
}
