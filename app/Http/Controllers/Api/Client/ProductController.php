<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
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
}
