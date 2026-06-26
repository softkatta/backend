<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Order;
use App\Services\BillingAdminService;
use Illuminate\Http\JsonResponse;

class OrderController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            Order::withoutGlobalScopes()
                ->with(['user', 'product', 'plan', 'tenant'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Order $order): JsonResponse
    {
        return $this->success(
            Order::withoutGlobalScopes()
                ->with(['user', 'product', 'plan', 'tenant', 'invoice', 'payments'])
                ->findOrFail($order->id)
        );
    }

    public function destroy(Order $order, BillingAdminService $billingAdmin): JsonResponse
    {
        $billingAdmin->deleteOrder($order);

        return $this->success(null, 'Order deleted.');
    }
}
