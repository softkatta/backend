<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Subscription;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceService
{
    public function generateInvoiceNumber(): string
    {
        return app(InvoiceProfileService::class)->allocateInvoiceNumber();
    }

    /**
     * @param  array{item_description?: string, billing_details?: array<string, mixed>, due_date?: string}  $options
     */
    public function generateFromOrder(Order $order, ?Subscription $subscription = null, array $options = []): Invoice
    {
        $order->load(['product', 'plan', 'user']);
        $user = $order->user;
        $plan = $order->plan;
        $isInterState = ($user->state ?? '') !== 'Maharashtra';

        $cgst = $isInterState ? 0 : round((float) $order->tax_amount / 2, 2);
        $sgst = $isInterState ? 0 : round((float) $order->tax_amount / 2, 2);
        $igst = $isInterState ? (float) $order->tax_amount : 0;

        $billingDetails = array_merge([
            'name' => $user->name,
            'company' => $user->company_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'city' => $user->city,
            'state' => $user->state,
            'pincode' => $user->pincode,
            'country' => $user->country,
        ], $options['billing_details'] ?? []);

        $invoice = Invoice::create([
            'tenant_id' => $order->tenant_id,
            'user_id' => $order->user_id,
            'subscription_id' => $subscription?->id,
            'order_id' => $order->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'subtotal' => $order->amount,
            'tax_amount' => $order->tax_amount,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'total_amount' => $order->total_amount,
            'status' => InvoiceStatus::Draft,
            'due_date' => $options['due_date'] ?? now()->addDays(7)->toDateString(),
            'gst_details' => [
                'gst_number' => app(InvoiceProfileService::class)->company()['gst_number'],
                'company' => app(InvoiceProfileService::class)->company()['name'],
                'customer_gst' => $user->gst_number,
            ],
            'billing_details' => $billingDetails,
        ]);

        $description = $options['item_description']
            ?? "{$order->product->name} — {$plan->name} ({$plan->billing_cycle->label()})";

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $order->amount,
            'tax_rate' => app(InvoiceProfileService::class)->gstRate(),
            'tax_amount' => $order->tax_amount,
            'total_amount' => $order->total_amount,
        ]);

        return $invoice->load('items');
    }

    public function markAsPaid(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        $invoice = $invoice->fresh();
        app(PaymentService::class)->recordFromPaidInvoice($invoice);

        return $invoice;
    }

    public function generatePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load(['items', 'user', 'order.plan.product']);
        $profile = app(InvoiceProfileService::class);
        $company = $profile->company();
        $payment = $this->paymentQrData($company, $invoice);

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'company' => $company,
            'logo_file' => $profile->logoAbsolutePath($company['logo'] ?? null),
            'signature_file' => $profile->logoAbsolutePath($company['signature'] ?? null),
            'terms' => $profile->terms(),
            'currency_code' => $profile->currency(),
            'colors' => config('invoice.colors'),
            'payment_qr_uri' => $payment['qr_uri'] ?? null,
        ])->setPaper('a4', 'portrait');
    }

    public function toDetailArray(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'user', 'order.plan.product']);
        $profile = app(InvoiceProfileService::class);
        $company = $profile->company();
        $payment = $this->paymentQrData($company, $invoice);

        return array_merge($invoice->toArray(), [
            'company_profile' => $company,
            'terms_text' => $profile->terms(),
            'currency' => $profile->currency(),
            'payment_qr_payload' => $payment['payload'] ?? null,
        ]);
    }

    /** @return array{payload: ?string, qr_uri: ?string} */
    private function paymentQrData(array $company, Invoice $invoice): array
    {
        $vpa = trim((string) ($company['upi_vpa'] ?? ''));
        if ($vpa === '') {
            return ['payload' => null, 'qr_uri' => null];
        }

        $upi = app(UpiPaymentService::class);
        $currency = app(InvoiceProfileService::class)->currency();
        $payload = $upi->buildPayload(
            $vpa,
            (string) $company['name'],
            (float) $invoice->total_amount,
            'Invoice '.$invoice->invoice_number,
            $currency,
        );

        return [
            'payload' => $payload,
            'qr_uri' => $upi->qrSvgDataUri($payload),
        ];
    }
}
