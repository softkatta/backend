<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Client\ClientBatchPurchaseRequest;
use App\Http\Requests\Client\ClientPurchaseRequest;
use App\Models\Plan;
use App\Models\Product;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;

class PurchaseController extends BaseApiController
{
    public function store(ClientPurchaseRequest $request, PurchaseService $purchaseService): JsonResponse
    {
        $product = Product::findOrFail($request->product_id);
        $plan = Plan::where('id', $request->plan_id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $result = $purchaseService->purchaseForExistingUser(
            $request->user(),
            $product,
            $plan,
            $request->payment_gateway,
            $request->coupon_code,
        );

        $message = ($result['requires_payment'] ?? false)
            ? 'Checkout initiated. Complete payment to activate your subscription.'
            : 'Purchase completed successfully.';

        return $this->success($result, $message, 201);
    }

    public function storeBatch(ClientBatchPurchaseRequest $request, PurchaseService $purchaseService): JsonResponse
    {
        $lineItems = collect($request->validated('items'))
            ->map(function (array $item): array {
                $product = Product::findOrFail($item['product_id']);
                $plan = Plan::query()
                    ->where('id', $item['plan_id'])
                    ->where('product_id', $product->id)
                    ->firstOrFail();

                return [
                    'product' => $product,
                    'plan' => $plan,
                ];
            })
            ->values()
            ->all();

        $result = $purchaseService->purchaseBatchForExistingUser(
            $request->user(),
            $lineItems,
            $request->payment_gateway,
            $request->coupon_code,
        );

        $message = ($result['requires_payment'] ?? false)
            ? 'Checkout initiated. Complete payment to activate your subscriptions.'
            : 'Purchase completed successfully.';

        return $this->success($result, $message, 201);
    }
}
