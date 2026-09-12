<?php

namespace Savv\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $streamEnabled = (bool) config('savv.runner.stream_enabled');

        // The dashboard iframes Savv's own /imports/{id}/browser route (never
        // a marketplace page directly), so frame-src/frame-ancestors allow
        // same-origin framing. connect-src additionally allows the same-
        // origin noVNC websocket proxy (see docs/deployment-ubuntu.md) only
        // when streaming is enabled.
        $connectSrc = $streamEnabled ? "'self' wss:" : "'self'";

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "frame-src 'self'",
            "connect-src {$connectSrc}",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
