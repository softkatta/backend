<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingAdminService
{
    /**
     * Create Order + Invoice + Payment for an admin-assigned subscription
     * so it appears under Admin → Orders / Invoices / Payments.
     *
     * @return array{order: Order, invoice: Invoice, payment: ?Payment}
     */
    public function createPaidBillingForSubscription(
        Subscription $subscription,
        string $paymentMethod = 'cash',
        ?string $reference = null,
    ): array {
        $subscription->loadMissing(['user', 'product', 'plan']);

        $existingInvoice = Invoice::withoutGlobalScopes()
            ->where('subscription_id', $subscription->id)
            ->latest('id')
            ->first();

        if ($existingInvoice) {
            $existingInvoice->loadMissing('order');
            $payment = Payment::withoutGlobalScopes()
                ->where('invoice_id', $existingInvoice->id)
                ->where('status', PaymentStatus::Completed)
                ->latest('id')
                ->first();

            return [
                'order' => $existingInvoice->order ?? Order::withoutGlobalScopes()->findOrFail($existingInvoice->order_id),
                'invoice' => $existingInvoice,
                'payment' => $payment,
            ];
        }

        $user = $subscription->user;
        $product = $subscription->product;
        $plan = $subscription->plan;

        if (! $user || ! $product || ! $plan) {
            throw ValidationException::withMessages([
                'subscription' => ['Subscription is missing user, product, or plan.'],
            ]);
        }

        $paymentMethod = in_array($paymentMethod, ['cash', 'cheque', 'manual', 'bank_transfer'], true)
            ? $paymentMethod
            : 'cash';

        return DB::transaction(function () use ($subscription, $user, $product, $plan, $paymentMethod, $reference) {
            $amount = round((float) $plan->price, 2);
            $taxRate = app(InvoiceProfileService::class)->gstRate();
            $taxAmount = round($amount * ($taxRate / 100), 2);
            $totalAmount = round($amount + $taxAmount, 2);

            $order = Order::create([
                'tenant_id' => $subscription->tenant_id ?? $user->tenant_id,
                'user_id' => $user->id,
                'product_id' => $product->id,
                'plan_id' => $plan->id,
                'order_number' => 'SK-ORD-'.strtoupper(\Illuminate\Support\Str::random(10)),
                'amount' => $amount,
                'discount_amount' => 0,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'completed',
                'payment_gateway' => $paymentMethod,
            ]);

            $invoice = app(InvoiceService::class)->generateFromOrder($order, $subscription, [
                'item_description' => "{$product->name} — {$plan->name} ({$plan->billing_cycle->label()}) [Admin assigned]",
                'due_date' => now()->toDateString(),
            ]);

            $transactionId = $this->manualTransactionId($paymentMethod, $invoice, $reference);
            $order->update(['payment_id' => $transactionId]);

            $invoice = app(InvoiceService::class)->markAsPaid($invoice);
            $payment = Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->where('status', PaymentStatus::Completed)
                ->latest('id')
                ->first();

            if ($payment && $paymentMethod !== 'manual') {
                $payment->update([
                    'gateway' => $paymentMethod,
                    'transaction_id' => $transactionId,
                    'gateway_response' => array_filter([
                        'source' => 'admin_subscription_create',
                        'payment_method' => $paymentMethod,
                        'reference' => $reference,
                        'recorded_at' => now()->toIso8601String(),
                    ]),
                ]);
            }

            return [
                'order' => $order->fresh(),
                'invoice' => $invoice->fresh(['items']),
                'payment' => $payment?->fresh(),
            ];
        });
    }

    /**
     * @return array{payment: Payment, invoice: Invoice}
     */
    public function recordManualPayment(
        Invoice $invoice,
        string $paymentMethod,
        ?string $reference = null,
        ?string $notes = null,
        ?Carbon $paidAt = null,
    ): array {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw ValidationException::withMessages([
                'invoice' => ['This invoice is already paid.'],
            ]);
        }

        return DB::transaction(function () use ($invoice, $paymentMethod, $reference, $notes, $paidAt) {
            $paidAt ??= now();
            $invoice->loadMissing('order');

            Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->where('status', PaymentStatus::Pending)
                ->delete();

            $invoice->update([
                'status' => InvoiceStatus::Paid,
                'paid_at' => $paidAt,
            ]);

            $transactionId = $this->manualTransactionId($paymentMethod, $invoice, $reference);

            if ($invoice->order) {
                $invoice->order->update([
                    'status' => 'completed',
                    'payment_gateway' => $paymentMethod,
                    'payment_id' => $transactionId,
                ]);
            }

            $this->activateSubscriptionForInvoice($invoice);

            $payment = Payment::create([
                'tenant_id' => $invoice->tenant_id,
                'user_id' => $invoice->user_id,
                'order_id' => $invoice->order_id,
                'invoice_id' => $invoice->id,
                'gateway' => $paymentMethod,
                'transaction_id' => $transactionId,
                'amount' => $invoice->total_amount,
                'status' => PaymentStatus::Completed,
                'gateway_response' => array_filter([
                    'source' => 'admin_manual',
                    'payment_method' => $paymentMethod,
                    'reference' => $reference,
                    'notes' => $notes,
                    'recorded_at' => now()->toIso8601String(),
                ]),
            ]);

            return [
                'payment' => $payment->fresh(['user', 'order', 'invoice']),
                'invoice' => $invoice->fresh(['user', 'order']),
            ];
        });
    }

    protected function manualTransactionId(string $paymentMethod, Invoice $invoice, ?string $reference): string
    {
        $reference = trim((string) $reference);

        if ($reference !== '') {
            return strtoupper($paymentMethod).'-'.$reference;
        }

        return strtoupper($paymentMethod).'-'.$invoice->invoice_number;
    }

    public function activateSubscriptionForInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('order');

        if (! $invoice->order?->product_id) {
            return;
        }

        $subscription = Subscription::query()
            ->where('user_id', $invoice->user_id)
            ->where('product_id', $invoice->order->product_id)
            ->where('plan_id', $invoice->order->plan_id)
            ->latest('id')
            ->first();

        if (! $subscription) {
            return;
        }

        if ($subscription->status === SubscriptionStatus::Pending) {
            $subscription->update(['status' => SubscriptionStatus::Active]);
            $subscription->refresh();
        }

        if (in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Trial], true)) {
            try {
                app(LicenseService::class)->generateForSubscription($subscription);
            } catch (\App\Exceptions\TenantDomainsRequiredException) {
                // Domains must be assigned in SoftKatta Admin → Tenants first.
            }
        }
    }

    public function resolveInvoiceForManualPayment(?string $invoiceId, ?string $orderId, ?string $subscriptionId = null): Invoice
    {
        if ($subscriptionId) {
            $subscription = Subscription::withoutGlobalScopes()->findOrFail($subscriptionId);

            $order = Order::withoutGlobalScopes()
                ->where('user_id', $subscription->user_id)
                ->where('product_id', $subscription->product_id)
                ->where('plan_id', $subscription->plan_id)
                ->latest('id')
                ->first();

            if (! $order) {
                throw ValidationException::withMessages([
                    'subscription_id' => ['No order found for this subscription.'],
                ]);
            }

            $orderId = (string) $order->id;
        }

        if ($invoiceId) {
            return Invoice::withoutGlobalScopes()->findOrFail($invoiceId);
        }

        $order = Order::withoutGlobalScopes()->findOrFail($orderId);
        $invoice = Invoice::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();

        if (! $invoice) {
            throw ValidationException::withMessages([
                'order_id' => ['No invoice found for this order.'],
            ]);
        }

        return $invoice;
    }

    public function deletePayment(Payment $payment): void
    {
        $payment = Payment::withoutGlobalScopes()->findOrFail($payment->id);
        $this->permanentlyDelete($payment);
    }

    public function deleteInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice = Invoice::withoutGlobalScopes()->findOrFail($invoice->id);

            Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->get()
                ->each(fn (Payment $payment) => $this->permanentlyDelete($payment));

            $invoice->items()->get()->each(fn (Model $item) => $this->permanentlyDelete($item));
            $this->permanentlyDelete($invoice);
        });
    }

    public function deleteOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::withoutGlobalScopes()->findOrFail($order->id);

            Payment::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->get()
                ->each(fn (Payment $payment) => $this->permanentlyDelete($payment));

            $invoices = Invoice::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->get();

            foreach ($invoices as $invoice) {
                $this->deleteInvoice($invoice);
            }

            $this->permanentlyDelete($order);
        });
    }

    public function deleteSubscription(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            $subscription = Subscription::withoutGlobalScopes()->findOrFail($subscription->id);

            Invoice::withoutGlobalScopes()
                ->where('subscription_id', $subscription->id)
                ->update(['subscription_id' => null]);

            $this->permanentlyDelete($subscription);
        });
    }

    private function permanentlyDelete(Model $model): void
    {
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $model->forceDelete();

            return;
        }

        $model->delete();
    }
}
