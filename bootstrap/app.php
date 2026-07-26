<?php

use App\Http\Middleware\EmployeePortalMenuMiddleware;
use App\Http\Middleware\EnforceSecurityPolicy;
use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureSiteNotInMaintenance;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\ForceCorsOnErrors;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\VerifyCompanyApiSignature;
use App\Http\Middleware\VerifyProductApiSignature;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

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
        $middleware->prepend(ForceCorsOnErrors::class);
        $middleware->prepend(HandleCors::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'employee.portal.menu' => EmployeePortalMenuMiddleware::class,
            'tenant' => EnsureTenantAccess::class,
            'maintenance' => EnsureSiteNotInMaintenance::class,
            'security.policy' => EnforceSecurityPolicy::class,
            'session.timeout' => EnforceSessionTimeout::class,
            'product.api' => VerifyProductApiSignature::class,
            'company.api' => VerifyCompanyApiSignature::class,
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        /*
         | Server वर फक्त एक cron ठेवा:
         | * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
         |
         | खालील सर्व jobs त्या एका cron मधूनच चालतात.
         */

        $schedule->command('softkatta:automate --task=subscriptions')
            ->hourly()
            ->withoutOverlapping()
            ->name('softkatta-subscriptions');

        $schedule->command('softkatta:automate --task=licenses')
            ->hourly()
            ->withoutOverlapping()
            ->name('softkatta-licenses');

        $schedule->command('softkatta:automate --task=invoices')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->name('softkatta-invoices');

        $schedule->command('softkatta:automate --task=payments')
            ->dailyAt('01:15')
            ->withoutOverlapping()
            ->name('softkatta-payments');

        $schedule->command('softkatta:automate --task=cleanup')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->name('softkatta-cleanup');

        $schedule->command('sanctum:prune-expired --hours=24')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->name('sanctum-prune');

        $schedule->command('subscriptions:notify-trial-end')
            ->dailyAt('02:05')
            ->withoutOverlapping()
            ->name('subscriptions-trial-notify');

        // Expire licenses whose expires_at has passed (temporary trial licenses)
        $schedule->command('licenses:expire')
            ->dailyAt('00:10')
            ->withoutOverlapping()
            ->name('licenses-expire');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
