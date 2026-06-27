<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BillingAdminService;
use App\Services\SecurityService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubscriptionController extends BaseApiController
{
    public function index(Request $request, SecurityService $security): JsonResponse
    {
        $query = Subscription::withoutGlobalScopes()
            ->with(['user', 'product', 'plan', 'tenant'])
            ->latest();
        $security->applyAdminWorkspaceScope($query, $request);

        return $this->success(
            $query->paginate(20)
        );
    }

    public function show(Request $request, Subscription $subscription, SecurityService $security): JsonResponse
    {
        $query = Subscription::withoutGlobalScopes()
            ->with(['user', 'product', 'plan', 'tenant', 'invoices']);
        $security->applyAdminWorkspaceScope($query, $request);

        return $this->success(
            $query->findOrFail($subscription->id)
        );
    }

    public function store(Request $request, TenantService $tenantService, SecurityService $security): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'status' => ['sometimes', 'string', 'in:active,pending,suspend,expiring_soon,expired'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'auto_renew' => ['sometimes', 'boolean'],
            'apply_trial' => ['sometimes', 'boolean'],
        ]);

        $user = User::findOrFail($data['user_id']);
        if ($user->role !== UserRole::Client) {
            return $this->error('Subscriptions can only be assigned to customer accounts.', 422);
        }

        if (! $security->isTenantAllowedForAdminWorkspace($user->tenant_id, $request)) {
            return $this->error('Selected customer is not available in the current admin workspace.', 422);
        }

        $product = Product::findOrFail($data['product_id']);
        $plan = Plan::findOrFail($data['plan_id']);

        if ((int) $plan->product_id !== (int) $product->id) {
            return $this->error('Selected plan does not belong to this product.', 422);
        }

        if (! $user->tenant_id) {
            $tenant = $tenantService->create([
                'name' => $user->company_name ?? $user->name.' Workspace',
            ], $user);
            $user->update(['tenant_id' => $tenant->id]);
            $user->refresh();
        }

        if (! $security->isTenantAllowedForAdminWorkspace($user->tenant_id, $request)) {
            return $this->error('Cannot create subscription in this admin workspace.', 422);
        }

        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();
        $endsAt = isset($data['ends_at'])
            ? Carbon::parse($data['ends_at'])
            : match ($plan->billing_cycle->value) {
                'yearly' => $startsAt->copy()->addYear(),
                'monthly' => $startsAt->copy()->addMonth(),
                default => $startsAt->copy()->addMonth(),
            };

        $applyTrial = $data['apply_trial'] ?? false;
        $trialEndsAt = $applyTrial && $product->has_free_trial
            ? $startsAt->copy()->addDays($product->trial_days)
            : null;

        $subscription = Subscription::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => $data['status'] ?? SubscriptionStatus::Active->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $trialEndsAt,
            'auto_renew' => $data['auto_renew'] ?? true,
        ]);

        return $this->success(
            $subscription->load(['user', 'product', 'plan', 'tenant']),
            'Subscription created.',
            201
        );
    }

    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,pending,suspend,expiring_soon,expired'],
            'auto_renew' => ['sometimes', 'boolean'],
            'ends_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
        ]);

        if (
            isset($data['status'])
            && in_array($data['status'], [SubscriptionStatus::ExpiringSoon->value, SubscriptionStatus::Expired->value, SubscriptionStatus::Suspend->value], true)
        ) {
            $data['auto_renew'] = $data['auto_renew'] ?? false;
            if (empty($subscription->cancelled_at)) {
                $data['cancelled_at'] = now();
            }
        }

        $subscription->update($data);

        return $this->success(
            $subscription->fresh()->load(['user', 'product', 'plan', 'tenant']),
            'Subscription updated.'
        );
    }

    public function cancel(Subscription $subscription): JsonResponse
    {
        $subscription->update([
            'auto_renew' => false,
            'cancelled_at' => now(),
            'status' => SubscriptionStatus::ExpiringSoon,
        ]);

        return $this->success(
            $subscription->fresh()->load(['user', 'product', 'plan', 'tenant']),
            'Subscription cancelled.'
        );
    }

    public function destroy(Request $request, Subscription $subscription, BillingAdminService $billingAdmin, SecurityService $security): JsonResponse
    {
        $query = Subscription::withoutGlobalScopes();
        $security->applyAdminWorkspaceScope($query, $request);
        $scopedSubscription = $query->findOrFail($subscription->id);

        $billingAdmin->deleteSubscription($scopedSubscription);

        return $this->success(null, 'Subscription deleted.');
    }
}
