<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Public\PurchaseRequest;
use App\Models\Plan;
use App\Models\Product;
use App\Services\PurchaseService;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $lite = $request->boolean('lite');

        $with = [
            'category:id,name,slug',
            'features' => fn ($q) => $q->orderBy('sort_order'),
            'plans' => fn ($q) => $q->where('is_active', true),
            'screenshots' => fn ($q) => $q->orderBy('sort_order'),
        ];

        if (! $lite) {
            $with['videos'] = fn ($q) => $q->orderBy('sort_order')->orderBy('id')->limit(1);
        }

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with($with)
            ->get();

        if ($lite) {
            $products->each(function (Product $product): void {
                $product->setRelation('features', $product->features->take(4)->values());
                $product->setRelation('screenshots', $product->screenshots->take(1)->values());
                $product->makeHidden(['overview', 'meta', 'banner', 'body']);
            });
        }

        return $this->success($products);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::with([
            'category',
            'features' => fn ($q) => $q->orderBy('sort_order'),
            'screenshots',
            'videos',
            'plans',
        ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->success($product);
    }

    public function purchase(PurchaseRequest $request, PurchaseService $purchaseService, RecaptchaService $recaptcha): JsonResponse
    {
        $recaptcha->verify($request->input('recaptcha_token'), $request->ip(), 'purchase');

        $product = Product::findOrFail($request->product_id);
        $plan = Plan::where('id', $request->plan_id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $result = $purchaseService->purchase(
            $product,
            $plan,
            $request->safe()->except(['recaptcha_token', 'product_id', 'plan_id', 'payment_gateway']),
            $request->payment_gateway
        );

        $token = $result['user']->createToken('softkatta-api')->plainTextToken;

        return $this->success([
            ...$result,
            'token' => $token,
        ], 'Purchase initiated successfully.', 201);
    }
}
