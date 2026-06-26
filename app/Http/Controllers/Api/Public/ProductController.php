<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Public\PurchaseRequest;
use App\Models\Plan;
use App\Models\Product;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;

class ProductController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $products = Product::with([
            'category',
            'plans' => fn ($q) => $q->where('is_active', true),
            'screenshots',
            'videos',
        ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success($products);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::with(['category', 'features', 'screenshots', 'videos', 'plans'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->success($product);
    }

    public function purchase(PurchaseRequest $request, PurchaseService $purchaseService): JsonResponse
    {
        $product = Product::findOrFail($request->product_id);
        $plan = Plan::where('id', $request->plan_id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $result = $purchaseService->purchase(
            $product,
            $plan,
            $request->validated(),
            $request->payment_gateway
        );

        $token = $result['user']->createToken('softkatta-api')->plainTextToken;

        return $this->success([
            ...$result,
            'token' => $token,
        ], 'Purchase initiated successfully.', 201);
    }
}
