<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteNotInMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $maintenance = app(MaintenanceService::class);

        if (! $maintenance->isEnabled()) {
            return $next($request);
        }

        $user = $request->user();
        if ($user?->isSuperAdmin()) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => $maintenance->message(),
            'maintenance' => true,
        ], 503);
    }
}
