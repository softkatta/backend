<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $products = Product::with(['plans' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success($products);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::with(['features', 'screenshots', 'videos', 'plans'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->success($product);
    }
}
