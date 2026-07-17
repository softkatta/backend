<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;

class CouponController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            Coupon::query()
                ->with('product:id,name')
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->validated());

        return $this->success($coupon->load('product:id,name'), 'Coupon created.', 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return $this->success($coupon->load('product:id,name'));
    }

    public function update(StoreCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($request->validated());

        return $this->success($coupon->fresh()->load('product:id,name'), 'Coupon updated.');
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->permanentlyDelete($coupon);

        return $this->success(null, 'Coupon deleted.');
    }
}
