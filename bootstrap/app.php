<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\BindActiveCompanyContext;
use App\Http\Middleware\EnsureUserAccountIsActive;
use App\Http\Middleware\EnsureRequestId;
use App\Http\Middleware\EnforceErpWriteRateLimit;
use App\Support\ExceptionResponseFactory;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(EnsureRequestId::class);
        $middleware->append(AddSecurityHeaders::class);

        $middleware->alias([
            'account.active' => EnsureUserAccountIsActive::class,
            'company.active' => BindActiveCompanyContext::class,
            'erp.write_limit' => EnforceErpWriteRateLimit::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, Request $request) {
            return app(ExceptionResponseFactory::class)->render($exception, $request);
        });
    })->create();
