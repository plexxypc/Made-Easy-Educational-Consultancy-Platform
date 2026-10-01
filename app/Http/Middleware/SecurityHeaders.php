<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standard security headers on every response Laravel produces.
 *
 * Caddy sets the same headers (docker/caddy/Caddyfile*) because it serves
 * static files without reaching PHP; Caddy's values replace these, so a
 * response never carries a header twice. Doing it here as well means the
 * headers don't depend on the proxy config being right, and are testable.
 *
 * HSTS is only sent over HTTPS and only when config('madeeasy.security.
 * hsts_max_age') is above 0 (HSTS_MAX_AGE in .env): once browsers have seen
 * it they refuse plain HTTP for that long, so it is switched on only after
 * HTTPS is confirmed working on the real domain (GO-LIVE.md).
 */
class SecurityHeaders
{
    /**
     * @param  Request  $request
     * @param  Closure(Request): Response  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $hsts_max_age = (int) config('madeeasy.security.hsts_max_age');

        if ($hsts_max_age > 0 && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.$hsts_max_age);
        }

        return $response;
    }
}
