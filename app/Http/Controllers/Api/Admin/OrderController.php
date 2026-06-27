<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Order;
use App\Services\BillingAdminService;
use App\Services\SecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseApiController
{
    public function index(Request $request, SecurityService $security): JsonResponse
    {
        $query = Order::withoutGlobalScopes()
            ->with(['user', 'product', 'plan', 'tenant'])
            ->latest();

        $security->applyAdminWorkspaceScope($query, $request);

        return $this->success(
            $query->paginate(20)
        );
    }

    public function show(Request $request, Order $order, SecurityService $security): JsonResponse
    {
        $query = Order::withoutGlobalScopes()
            ->with(['user', 'product', 'plan', 'tenant', 'invoice', 'payments']);

        $security->applyAdminWorkspaceScope($query, $request);

        return $this->success(
            $query->findOrFail($order->id)
        );
    }

    public function destroy(Request $request, Order $order, BillingAdminService $billingAdmin, SecurityService $security): JsonResponse
    {
        $query = Order::withoutGlobalScopes();
        $security->applyAdminWorkspaceScope($query, $request);
        $scopedOrder = $query->findOrFail($order->id);

        $billingAdmin->deleteOrder($scopedOrder);

        return $this->success(null, 'Order deleted.');
    }
}
