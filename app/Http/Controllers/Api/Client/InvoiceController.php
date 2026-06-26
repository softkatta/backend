<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::with('items')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return $this->success($invoices);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success(
            app(InvoiceService::class)->toDetailArray($invoice)
        );
    }

    public function download(Request $request, Invoice $invoice, InvoiceService $invoiceService)
    {
        if ($invoice->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        $pdf = $invoiceService->generatePdf($invoice);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}
