<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user->tenant_id) {
            // Checkout creates a workspace on first purchase; allow that flow.
            if ($request->isMethod('POST') && str_ends_with($request->path(), 'client/purchase')) {
                return $next($request);
            }

            return response()->json(['message' => 'No tenant associated with this account.'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated.'], 403);
        }

        $tenantId = $request->route('tenant') ?? $request->input('tenant_id');

        if ($tenantId && $tenantId !== $user->tenant_id) {
            return response()->json(['message' => 'Unauthorized tenant access.'], 403);
        }

        return $next($request);
    }
}
