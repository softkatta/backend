<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Payment;
use App\Services\BillingAdminService;
use Illuminate\Http\JsonResponse;

class PaymentController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            Payment::withoutGlobalScopes()
                ->with(['user', 'order', 'invoice'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Payment $payment): JsonResponse
    {
        return $this->success(
            Payment::withoutGlobalScopes()
                ->with(['user', 'order', 'invoice', 'tenant'])
                ->findOrFail($payment->id)
        );
    }

    public function destroy(Payment $payment, BillingAdminService $billingAdmin): JsonResponse
    {
        $billingAdmin->deletePayment($payment);

        return $this->success(null, 'Payment deleted.');
    }
}
