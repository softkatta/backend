<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
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

        // Tenant project access requires an active (or currently expiring) subscription.
        $hasEntitledSubscription = Subscription::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::ExpiringSoon->value])
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->exists();

        if (! $hasEntitledSubscription) {
            return response()->json([
                'message' => 'Subscription inactive or expired. Project access is blocked.',
                'errors' => ['code' => 'SUBSCRIPTION_INACTIVE'],
            ], 403);
        }

        $tenantId = $request->route('tenant') ?? $request->input('tenant_id');

        if ($tenantId && $tenantId !== $user->tenant_id) {
            return response()->json(['message' => 'Unauthorized tenant access.'], 403);
        }

        return $next($request);
    }
}
