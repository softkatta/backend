<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\CashfreeGateway;
use App\Services\Payment\PayuGateway;
use App\Services\Payment\RazorpayGateway;
use App\Services\Payment\StripeGateway;
use InvalidArgumentException;

class PaymentService
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $gateways = [];

    public function __construct()
    {
        $this->registerGateway(new RazorpayGateway);
        $this->registerGateway(new StripeGateway);
        $this->registerGateway(new PayuGateway);
        $this->registerGateway(new CashfreeGateway);
    }

    public function registerGateway(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->getName()] = $gateway;
    }

    public function gateway(string $name): PaymentGatewayInterface
    {
        if (! isset($this->gateways[$name])) {
            throw new InvalidArgumentException("Payment gateway [{$name}] is not registered.");
        }

        return $this->gateways[$name];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payment: Payment, checkout: array<string, mixed>}
     */
    public function initiate(Order $order, string $gateway, array $payload = []): array
    {
        $order->loadMissing('invoice');
        $response = $this->gateway($gateway)->initiatePayment($order, $payload);

        $payment = Payment::create([
            'tenant_id' => $order->tenant_id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'invoice_id' => $order->invoice?->id,
            'gateway' => $gateway,
            'transaction_id' => $response['transaction_id'] ?? $response['razorpay_order_id'] ?? null,
            'amount' => $order->total_amount,
            'status' => PaymentStatus::Pending,
            'gateway_response' => $response,
        ]);

        return [
            'payment' => $payment,
            'checkout' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(Payment $payment, array $payload = []): bool
    {
        $verified = $this->gateway($payment->gateway)->verifyPayment($payment, $payload);

        $payment->update([
            'status' => $verified ? PaymentStatus::Completed : PaymentStatus::Failed,
            'transaction_id' => $verified
                ? ($payload['razorpay_payment_id'] ?? $payment->transaction_id)
                : $payment->transaction_id,
            'gateway_response' => array_merge($payment->gateway_response ?? [], $payload),
        ]);

        return $verified;
    }

    public function recordFromPaidInvoice(Invoice $invoice, string $fallbackGateway = 'manual'): ?Payment
    {
        if ($invoice->status !== InvoiceStatus::Paid) {
            return null;
        }

        $existing = Payment::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentStatus::Completed)
            ->first();

        if ($existing) {
            return $existing;
        }

        $invoice->loadMissing('order');

        $orderPaymentId = $invoice->order?->payment_id;
        $gateway = $orderPaymentId
            ? ($invoice->order?->payment_gateway ?? $fallbackGateway)
            : $fallbackGateway;

        return Payment::create([
            'tenant_id' => $invoice->tenant_id,
            'user_id' => $invoice->user_id,
            'order_id' => $invoice->order_id,
            'invoice_id' => $invoice->id,
            'gateway' => $gateway,
            'transaction_id' => $orderPaymentId ?? ('MANUAL-'.$invoice->invoice_number),
            'amount' => $invoice->total_amount,
            'status' => PaymentStatus::Completed,
            'gateway_response' => [
                'source' => 'invoice_paid',
                'recorded_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function syncFromPaidInvoices(): int
    {
        $created = 0;

        Invoice::withoutGlobalScopes()
            ->where('status', InvoiceStatus::Paid)
            ->with('order')
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use (&$created): void {
                foreach ($invoices as $invoice) {
                    $before = Payment::withoutGlobalScopes()
                        ->where('invoice_id', $invoice->id)
                        ->where('status', PaymentStatus::Completed)
                        ->exists();

                    $this->recordFromPaidInvoice($invoice);

                    if (! $before) {
                        $created++;
                    }
                }
            });

        return $created;
    }
}
