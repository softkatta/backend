<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\BillingAdminService;
use App\Services\InvoiceService;
use App\Services\SecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseApiController
{
    public function index(Request $request, SecurityService $security): JsonResponse
    {
        $query = Invoice::withoutGlobalScopes()
            ->with(['user', 'tenant'])
            ->latest();

        $security->applyAdminWorkspaceScope($query, $request);

        return $this->success(
            $query->paginate(20)
        );
    }

    public function show(Request $request, Invoice $invoice, InvoiceService $service, SecurityService $security): JsonResponse
    {
        $query = Invoice::withoutGlobalScopes()
            ->with(['items', 'user', 'tenant', 'order.plan.product']);
        $security->applyAdminWorkspaceScope($query, $request);
        $invoice = $query->findOrFail($invoice->id);

        return $this->success($service->toDetailArray($invoice));
    }

    public function update(Request $request, Invoice $invoice, InvoiceService $service, SecurityService $security): JsonResponse
    {
        $invoiceQuery = Invoice::withoutGlobalScopes();
        $security->applyAdminWorkspaceScope($invoiceQuery, $request);
        $scopedInvoice = $invoiceQuery->findOrFail($invoice->id);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:draft,sent,paid,overdue,cancelled,refunded'],
        ]);

        if (($data['status'] ?? null) === 'paid') {
            $invoiceWithOrderQuery = Invoice::withoutGlobalScopes()->with('order');
            $security->applyAdminWorkspaceScope($invoiceWithOrderQuery, $request);
            $scopedInvoice = $invoiceWithOrderQuery->findOrFail($invoice->id);
            $service->markAsPaid($scopedInvoice);

            if ($scopedInvoice->order) {
                $scopedInvoice->order->update(['status' => 'completed']);
            }

            if ($scopedInvoice->order?->product_id) {
                Subscription::query()
                    ->where('user_id', $scopedInvoice->user_id)
                    ->where('product_id', $scopedInvoice->order->product_id)
                    ->where('status', SubscriptionStatus::Pending)
                    ->latest('id')
                    ->limit(1)
                    ->update(['status' => SubscriptionStatus::Active]);
            }
        } else {
            $scopedInvoice->update($data);
        }

        return $this->success($scopedInvoice->fresh(), 'Invoice updated.');
    }

    public function download(Request $request, Invoice $invoice, InvoiceService $service, SecurityService $security)
    {
        $query = Invoice::withoutGlobalScopes();
        $security->applyAdminWorkspaceScope($query, $request);
        $invoice = $query->findOrFail($invoice->id);
        $pdf = $service->generatePdf($invoice);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    public function destroy(Request $request, Invoice $invoice, BillingAdminService $billingAdmin, SecurityService $security): JsonResponse
    {
        $query = Invoice::withoutGlobalScopes();
        $security->applyAdminWorkspaceScope($query, $request);
        $scopedInvoice = $query->findOrFail($invoice->id);

        $billingAdmin->deleteInvoice($scopedInvoice);

        return $this->success(null, 'Invoice deleted.');
    }
}
