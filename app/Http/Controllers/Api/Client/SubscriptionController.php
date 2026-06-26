<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $subscriptions = Subscription::with(['product', 'plan'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return $this->success($subscriptions);
    }

    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success($subscription->load(['product', 'plan', 'invoices']));
    }

    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        $subscription->update([
            'auto_renew' => false,
            'cancelled_at' => now(),
            'status' => 'expiring_soon',
        ]);

        return $this->success($subscription->fresh(), 'Subscription cancellation scheduled.');
    }
}
