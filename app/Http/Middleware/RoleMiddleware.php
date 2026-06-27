<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (empty($roles)) {
            return $next($request);
        }

        $security = app(SecurityService::class);
        if ($security->isDemoAccount($user) && str_starts_with(trim($request->path(), '/'), 'api/v1/admin/')) {
            return response()->json(['message' => 'Demo account can only access demo workspace data.'], 403);
        }

        $userRole = $user->role?->value ?? $user->role;

        if (! in_array($userRole, $roles, true) && ! $user->hasAnyRole($roles)) {
            return response()->json(['message' => 'Forbidden. Insufficient permissions.'], 403);
        }

        return $next($request);
    }
}
