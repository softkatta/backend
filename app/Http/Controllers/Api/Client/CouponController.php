<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Client\ValidateCouponRequest;
use App\Models\Plan;
use App\Models\Product;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;

class CouponController extends BaseApiController
{
    public function validateCode(ValidateCouponRequest $request, CouponService $couponService): JsonResponse
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
                    'amount' => (float) $plan->price,
                ];
            })
            ->values()
            ->all();

        $result = $couponService->validateForCheckout(
            $request->user(),
            $request->validated('coupon_code'),
            $lineItems
        );

        return $this->success([
            'code' => $result['coupon']->code,
            'name' => $result['coupon']->name,
            'type' => $result['coupon']->type,
            'value' => (float) $result['coupon']->value,
            'subtotal' => $result['subtotal'],
            'discount_amount' => $result['discount_amount'],
            'total_after_discount' => round($result['subtotal'] - $result['discount_amount'], 2),
            'line_discounts' => $result['line_discounts'],
        ], 'Coupon applied.');
    }
}
