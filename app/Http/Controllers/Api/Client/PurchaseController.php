<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
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
            $request->payment_gateway
        );

        $message = ($result['requires_payment'] ?? false)
            ? 'Checkout initiated. Complete payment to activate your subscription.'
            : 'Purchase completed successfully.';

        return $this->success($result, $message, 201);
    }
}
