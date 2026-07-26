<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\PurchaseService;
use App\Services\SecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $security = app(SecurityService::class);
        $restrictToDemoTenant = $security->isDemoAccount($user) && ! empty($user->tenant_id);

        $subscriptionQuery = Subscription::withoutGlobalScope('tenant')
            ->where('user_id', $user->id);

        if ($restrictToDemoTenant) {
            $subscriptionQuery->where('tenant_id', $user->tenant_id);
        }

        $subscriptionProductIds = $subscriptionQuery->pluck('product_id')
            ->unique()
            ->values();

        $ordersQuery = Order::withoutGlobalScope('tenant')
            ->where('user_id', $user->id)
            ->where('status', 'completed');

        if ($restrictToDemoTenant) {
            $ordersQuery->where('tenant_id', $user->tenant_id);
        }

        $orderedProductIds = $ordersQuery->pluck('product_id')
            ->unique()
            ->values();

        $purchasedProductIds = $subscriptionProductIds
            ->merge($orderedProductIds)
            ->filter()
            ->unique()
            ->values();

        if ($purchasedProductIds->isEmpty()) {
            return $this->success([]);
        }

        $products = Product::with([
            'category',
            'screenshots',
            'plans' => fn ($q) => $q->where('is_active', true),
        ])
            ->whereIn('id', $purchasedProductIds)
            ->orderBy('sort_order')
            ->get();

        return $this->success($products);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $isPurchased = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('product', fn ($query) => $query->where('slug', $slug))
            ->exists();

        if (! $isPurchased) {
            return $this->error('Unauthorized.', 403);
        }

        $product = Product::with(['features', 'screenshots', 'videos', 'plans'])
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->success($product);
    }

    public function startTrial(Request $request, string $slug, PurchaseService $purchaseService): JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        if (! $product->has_free_trial) {
            return $this->error('This product does not offer a free trial.', 422);
        }

        // Prevent multiple trials per user for the same product
        $already = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->whereNotNull('trial_ends_at')
            ->exists();

        if ($already) {
            return $this->error('A free trial has already been used for your account.', 422);
        }

        // Pick a default active plan for the product
        $plan = $product->plans()->where('is_active', true)->orderBy('sort_order')->first();

        if (! $plan) {
            return $this->error('No active plan available for trial.', 422);
        }

        $result = $purchaseService->purchaseForExistingUser($request->user(), $product, $plan, null, null);

        return $this->success($result, 'Free trial started. You can use the product for the trial period.');
    }
}
