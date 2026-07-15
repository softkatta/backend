<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            // Allow onboarding and purchase flows before tenant provisioning.
            if ($this->isTenantOptionalPath($request)) {
                return $next($request);
            }

            return response()->json(['message' => 'No tenant associated with this account.'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated.'], 403);
        }

        if ($this->requiresEntitledSubscription($request)) {
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
        }

        $tenantId = $request->route('tenant') ?? $request->input('tenant_id');

        if ($tenantId && $tenantId !== $user->tenant_id) {
            return response()->json(['message' => 'Unauthorized tenant access.'], 403);
        }

        return $next($request);
    }

    private function isTenantOptionalPath(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if ($request->isMethod('POST') && Str::endsWith($path, 'client/purchase')) {
            return true;
        }

        foreach ([
            'api/v1/client/dashboard',
            'api/v1/client/products',
            'api/v1/client/subscriptions',
            'api/v1/client/licenses',
            'api/v1/client/invoices',
            'api/v1/client/notifications',
            'api/v1/client/support',
            'api/v1/client/profile',
        ] as $prefix) {
            if ($path === $prefix || Str::startsWith($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function requiresEntitledSubscription(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach ([
            'api/v1/client/dashboard',
            'api/v1/client/products',
            'api/v1/client/purchase',
            'api/v1/client/payments/verify',
            'api/v1/client/subscriptions',
            'api/v1/client/licenses',
            'api/v1/client/invoices',
            'api/v1/client/notifications',
            'api/v1/client/support',
            'api/v1/client/profile',
        ] as $prefix) {
            if ($path === $prefix || Str::startsWith($path, $prefix.'/')) {
                return false;
            }
        }

        return true;
    }
}
