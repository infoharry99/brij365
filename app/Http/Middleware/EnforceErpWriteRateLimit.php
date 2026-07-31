<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnforceErpWriteRateLimit
{
    /**
     * Rate-limit authenticated state-changing ERP requests.
     *
     * Safe HTTP methods are intentionally ignored here because they are covered
     * by the standard route throttle applied to the authenticated ERP group.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $maxAttempts = max(1, (int) config('security.rate_limits.erp_write_per_minute', 600));
        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many state-changing ERP requests. Please retry after '.$retryAfter.' seconds.',
            ], 429)->withHeaders([
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Limit' => (string) $maxAttempts,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $maxAttempts));

        return $response;
    }

    private function throttleKey(Request $request): string
    {
        $actor = $request->user()?->getAuthIdentifier() ?: 'guest';

        return 'erp-write:'.$actor.'|'.$request->ip();
    }
}
