<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Plan;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central Product/Plan catalogue — public, used by SoftKatta products to
 * display available plans & prompt for renewals/upgrades.
 */
class ProductController extends BaseApiController
{
    /**
     * GET /api/v1/central/products
     * Returns all active products with their active plans.
     */
    public function index(): JsonResponse
    {
        $products = Product::with(['plans' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Product $p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'slug'        => $p->slug,
                'description' => $p->description,
                'logo'        => $p->logo,
                'plans'       => $p->plans->map(fn (Plan $plan) => $this->formatPlan($plan)),
            ]);

        return $this->success($products);
    }

    /**
     * GET /api/v1/central/products/{slug}/plans
     * Returns active plans for a specific product.
     */
    public function plans(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->where('is_active', true)->first();

        if (! $product) {
            return $this->error('Product not found.', 404);
        }

        $plans = $product->plans()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => $this->formatPlan($plan));

        return $this->success($plans);
    }

    private function formatPlan(Plan $plan): array
    {
        return [
            'id'            => $plan->id,
            'name'          => $plan->name,
            'slug'          => $plan->slug,
            'description'   => $plan->description,
            'price'         => $plan->price,
            'discount'      => $plan->discount,
            'gst_rate'      => $plan->gst_rate,
            'currency'      => $plan->currency,
            'trial_days'    => $plan->trial_days,
            'billing_cycle' => $plan->billing_cycle->value,
            'billing_label' => $plan->billing_cycle->label(),
            'features'      => $plan->features ?? [],
            'limits'        => $plan->limits ?? [],
            'is_popular'    => $plan->is_popular,
        ];
    }
}
