<?php

use App\Http\Middleware\EnsureSiteNotInMaintenance;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HandleCors::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'tenant' => EnsureTenantAccess::class,
            'maintenance' => EnsureSiteNotInMaintenance::class,
            'security.policy' => \App\Http\Middleware\EnforceSecurityPolicy::class,
            'session.timeout' => \App\Http\Middleware\EnforceSessionTimeout::class,
            'product.api' => \App\Http\Middleware\VerifyProductApiSignature::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
