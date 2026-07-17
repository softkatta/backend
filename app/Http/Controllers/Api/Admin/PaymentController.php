<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\RecordManualPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingAdminService;
use App\Services\SecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    public function record(RecordManualPaymentRequest $request, BillingAdminService $billingAdmin, SecurityService $security): JsonResponse
    {
        $invoice = $billingAdmin->resolveInvoiceForManualPayment(
            $request->input('invoice_id'),
            $request->input('order_id'),
            $request->input('subscription_id'),
        );

        if ($request->filled('payment_id')) {
            $paymentQuery = Payment::withoutGlobalScopes();
            $security->applyAdminWorkspaceScope($paymentQuery, $request);
            $scopedPayment = $paymentQuery->findOrFail($request->integer('payment_id'));

            if ($scopedPayment->invoice_id) {
                $invoice = Invoice::withoutGlobalScopes()->findOrFail($scopedPayment->invoice_id);
            } elseif ($scopedPayment->order_id) {
                $invoice = $billingAdmin->resolveInvoiceForManualPayment(null, (string) $scopedPayment->order_id);
            }
        }

        $invoiceQuery = Invoice::withoutGlobalScopes();
        $security->applyAdminWorkspaceScope($invoiceQuery, $request);
        $invoiceQuery->findOrFail($invoice->id);

        $paidAt = $request->filled('paid_at')
            ? Carbon::parse($request->input('paid_at'))
            : null;

        $result = $billingAdmin->recordManualPayment(
            $invoice,
            $request->string('payment_method')->toString(),
            $request->input('reference'),
            $request->input('notes'),
            $paidAt,
        );

        return $this->success($result, 'Payment recorded successfully.', 201);
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
