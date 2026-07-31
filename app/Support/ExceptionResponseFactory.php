<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExceptionResponseFactory
{
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $this->shouldRenderJson($request)) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return null;
        }

        $status = $this->statusCode($exception);
        $payload = [
            'message' => $this->message($exception, $status),
        ];

        $requestId = $this->requestId($request);

        if ((bool) config('security.exception_responses.json_request_id_enabled', true) && $requestId !== null) {
            $payload['request_id'] = $requestId;
        }

        if ($this->debugDetailsEnabled()) {
            $payload['exception'] = class_basename($exception);
            $payload['file'] = $exception->getFile();
            $payload['line'] = $exception->getLine();
        }

        $response = response()->json($payload, $status);

        if ($requestId !== null) {
            $response->headers->set('X-Request-Id', $requestId);
        }

        if ($exception instanceof HttpExceptionInterface) {
            foreach ($exception->getHeaders() as $name => $value) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function shouldRenderJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->wantsJson()
            || $request->is('api/*')
            || str_contains((string) $request->header('Accept'), 'application/json');
    }

    private function statusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        if ($exception instanceof AuthenticationException) {
            return 401;
        }

        if ($exception instanceof AuthorizationException) {
            return 403;
        }

        if ($exception instanceof ModelNotFoundException) {
            return 404;
        }

        return 500;
    }

    private function message(Throwable $exception, int $status): string
    {
        if ($status >= 500 && ! $this->debugDetailsEnabled()) {
            return (string) config(
                'security.exception_responses.generic_server_error_message',
                'An unexpected server error occurred. Provide the request_id to support.',
            );
        }

        $message = trim($exception->getMessage());

        if ($message !== '') {
            return $message;
        }

        return HttpResponse::$statusTexts[$status] ?? 'Request failed.';
    }

    private function debugDetailsEnabled(): bool
    {
        return (bool) config('app.debug')
            && (bool) config('security.exception_responses.include_debug_details', false);
    }

    private function requestId(Request $request): ?string
    {
        $requestId = $request->attributes->get('request_id')
            ?: $request->headers->get('X-Request-Id')
            ?: $request->headers->get('X-Correlation-Id')
            ?: $request->headers->get('Traceparent');

        if (! is_string($requestId) || trim($requestId) === '') {
            return null;
        }

        return substr(trim($requestId), 0, 120);
    }
}
