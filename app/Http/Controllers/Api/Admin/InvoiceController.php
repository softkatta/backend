<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\BillingAdminService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            Invoice::withoutGlobalScopes()
                ->with(['user', 'tenant'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Invoice $invoice, InvoiceService $service): JsonResponse
    {
        $invoice = Invoice::withoutGlobalScopes()
            ->with(['items', 'user', 'tenant', 'order.plan.product'])
            ->findOrFail($invoice->id);

        return $this->success($service->toDetailArray($invoice));
    }

    public function update(Request $request, Invoice $invoice, InvoiceService $service): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:draft,sent,paid,overdue,cancelled,refunded'],
        ]);

        if (($data['status'] ?? null) === 'paid') {
            $invoice = Invoice::withoutGlobalScopes()->with('order')->findOrFail($invoice->id);
            $service->markAsPaid($invoice);

            if ($invoice->order) {
                $invoice->order->update(['status' => 'completed']);
            }

            if ($invoice->order?->product_id) {
                Subscription::query()
                    ->where('user_id', $invoice->user_id)
                    ->where('product_id', $invoice->order->product_id)
                    ->where('status', SubscriptionStatus::Pending)
                    ->latest('id')
                    ->limit(1)
                    ->update(['status' => SubscriptionStatus::Active]);
            }
        } else {
            $invoice->update($data);
        }

        return $this->success($invoice->fresh(), 'Invoice updated.');
    }

    public function download(Invoice $invoice, InvoiceService $service)
    {
        $invoice = Invoice::withoutGlobalScopes()->findOrFail($invoice->id);
        $pdf = $service->generatePdf($invoice);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    public function destroy(Invoice $invoice, BillingAdminService $billingAdmin): JsonResponse
    {
        $billingAdmin->deleteInvoice($invoice);

        return $this->success(null, 'Invoice deleted.');
    }
}
