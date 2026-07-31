<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);

        $request->headers->set('X-Request-Id', $requestId);
        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            $payload = $response->getData(true);

            if (is_array($payload) && ! array_key_exists('request_id', $payload)) {
                $payload['request_id'] = $requestId;
                $response->setData($payload);
            }
        }

        return $response;
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->headers->get('X-Request-Id')
            ?: $request->headers->get('X-Correlation-Id')
            ?: $request->headers->get('Traceparent');

        if (is_string($requestId) && trim($requestId) !== '') {
            return substr(trim($requestId), 0, 120);
        }

        return (string) Str::uuid();
    }
}
