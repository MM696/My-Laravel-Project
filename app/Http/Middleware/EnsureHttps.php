<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!env('APP_FORCE_HTTPS', false)) {
            return $next($request);
        }

        // When behind a proxy/load balancer, Laravel may already populate scheme via trust proxies.
        if (!$request->isSecure()) {
            return response()->json([
                'success' => false,
                'message' => 'HTTPS is required',
            ], 426);
        }

        return $next($request);
    }
}

