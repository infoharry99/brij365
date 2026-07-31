<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (config('security.headers', []) as $name => $value) {
            if (is_string($value) && $value !== '' && ! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() && (bool) config('security.hsts.enabled', true)) {
            $response->headers->set('Strict-Transport-Security', $this->hstsHeader());
        }

        if ($request->user() !== null && (bool) config('security.authenticated_cache.no_store_enabled', true)) {
            $this->addAuthenticatedNoStoreHeaders($response);
        }

        return $response;
    }

    private function addAuthenticatedNoStoreHeaders(Response $response): void
    {
        $cacheControl = (string) config(
            'security.authenticated_cache.cache_control',
            'private, no-store, max-age=0, must-revalidate'
        );

        if (! str_contains(strtolower((string) $response->headers->get('Cache-Control')), 'no-store')) {
            $response->headers->set('Cache-Control', $cacheControl);
        }

        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
    }

    private function hstsHeader(): string
    {
        $directives = [
            'max-age='.(int) config('security.hsts.max_age', 31536000),
        ];

        if ((bool) config('security.hsts.include_subdomains', true)) {
            $directives[] = 'includeSubDomains';
        }

        if ((bool) config('security.hsts.preload', false)) {
            $directives[] = 'preload';
        }

        return implode('; ', $directives);
    }
}
