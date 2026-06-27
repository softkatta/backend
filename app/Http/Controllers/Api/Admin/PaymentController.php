<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Payment;
use App\Services\BillingAdminService;
use App\Services\SecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseApiController
{
    public function index(Request $request, SecurityService $security): JsonResponse
    {
        $query = Payment::withoutGlobalScopes()
            ->with(['user', 'order', 'invoice'])
            ->latest();

        $security->applyAdminWorkspaceScope($query, $request);

        return $this->success(
            $query->paginate(20)
        );
    }

    public function show(Request $request, Payment $payment, SecurityService $security): JsonResponse
    {
        $query = Payment::withoutGlobalScopes()
            ->with(['user', 'order', 'invoice', 'tenant']);
        $security->applyAdminWorkspaceScope($query, $request);

        return $this->success(
            $query->findOrFail($payment->id)
        );
    }

    public function destroy(Request $request, Payment $payment, BillingAdminService $billingAdmin, SecurityService $security): JsonResponse
    {
        $query = Payment::withoutGlobalScopes();
        $security->applyAdminWorkspaceScope($query, $request);
        $scopedPayment = $query->findOrFail($payment->id);

        $billingAdmin->deletePayment($scopedPayment);

        return $this->success(null, 'Payment deleted.');
    }
}
