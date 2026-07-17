<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::withoutGlobalScope('tenant')
            ->with(['product', 'plan', 'invoice'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return $this->success($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success(
            $order->load(['product', 'plan', 'invoice', 'payments'])
        );
    }
}
