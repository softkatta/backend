<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\LicenseKey;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingAdminService
{
    /**
     * Create Order + Invoice + pending Payment for an admin-assigned subscription.
     * Payment stays pending until admin records cash / cheque / online receipt.
     *
     * @return array{order: Order, invoice: Invoice, payment: ?Payment}
     */
    public function createPendingBillingForSubscription(Subscription $subscription): array
    {
        $subscription->loadMissing(['user', 'product', 'plan']);

        $existingInvoice = Invoice::withoutGlobalScopes()
            ->where('subscription_id', $subscription->id)
            ->latest('id')
            ->first();

        if ($existingInvoice) {
            $existingInvoice->loadMissing('order');
            $payment = Payment::withoutGlobalScopes()
                ->where('invoice_id', $existingInvoice->id)
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

        return DB::transaction(function () use ($subscription, $user, $product, $plan) {
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
                'status' => 'pending',
                'payment_gateway' => 'manual',
            ]);

            $invoice = app(InvoiceService::class)->generateFromOrder($order, $subscription, [
                'item_description' => "{$product->name} — {$plan->name} ({$plan->billing_cycle->label()}) [Admin assigned]",
                'due_date' => now()->addDays(7)->toDateString(),
            ]);

            $invoice->update(['status' => InvoiceStatus::Sent]);

            $payment = Payment::create([
                'tenant_id' => $order->tenant_id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'gateway' => 'manual',
                'transaction_id' => 'PENDING-'.$invoice->invoice_number,
                'amount' => $totalAmount,
                'status' => PaymentStatus::Pending,
                'gateway_response' => [
                    'source' => 'admin_subscription_create',
                    'recorded_at' => now()->toIso8601String(),
                ],
            ]);

            return [
                'order' => $order->fresh(),
                'invoice' => $invoice->fresh(['items']),
                'payment' => $payment->fresh(),
            ];
        });
    }

    /**
     * @deprecated Use createPendingBillingForSubscription — kept for older callers.
     *
     * @return array{order: Order, invoice: Invoice, payment: ?Payment}
     */
    public function createPaidBillingForSubscription(
        Subscription $subscription,
        string $paymentMethod = 'cash',
        ?string $reference = null,
    ): array {
        return $this->createPendingBillingForSubscription($subscription);
    }

    /**
     * @return array{payment: Payment, invoice: Invoice, amount_due: float}
     */
    public function recordManualPayment(
        Invoice $invoice,
        string $paymentMethod,
        ?string $reference = null,
        ?string $notes = null,
        ?Carbon $paidAt = null,
        ?float $amount = null,
    ): array {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw ValidationException::withMessages([
                'invoice' => ['This invoice is already paid.'],
            ]);
        }

        $paymentMethod = in_array($paymentMethod, ['cash', 'cheque', 'online', 'bank_transfer', 'manual'], true)
            ? $paymentMethod
            : 'cash';

        return DB::transaction(function () use ($invoice, $paymentMethod, $reference, $notes, $paidAt, $amount) {
            $paidAt ??= now();
            $invoice->loadMissing('order');

            $invoiceTotal = round((float) $invoice->total_amount, 2);
            $alreadyPaid = round((float) Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->where('status', PaymentStatus::Completed)
                ->sum('amount'), 2);
            $remaining = round($invoiceTotal - $alreadyPaid, 2);

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'invoice' => ['This invoice has no remaining balance.'],
                ]);
            }

            $payAmount = $amount !== null ? round($amount, 2) : $remaining;
            if ($payAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Enter a payment amount greater than zero.'],
                ]);
            }
            if ($payAmount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => ["Amount cannot exceed remaining due ({$remaining})."],
                ]);
            }

            // Clear placeholder pending rows — remaining balance is recreated if needed.
            Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->where('status', PaymentStatus::Pending)
                ->delete();

            $transactionId = $this->manualTransactionId($paymentMethod, $invoice, $reference);
            $fullyPaid = $payAmount >= $remaining;

            if ($invoice->order) {
                $invoice->order->update([
                    'status' => $fullyPaid ? 'completed' : 'pending',
                    'payment_gateway' => $paymentMethod,
                    'payment_id' => $fullyPaid ? $transactionId : ($invoice->order->payment_id),
                ]);
            }

            if ($fullyPaid) {
                $invoice->update([
                    'status' => InvoiceStatus::Paid,
                    'paid_at' => $paidAt,
                ]);
                $this->finalizeSubscriptionForPaidInvoice($invoice);
            } elseif ($invoice->status === InvoiceStatus::Draft) {
                $invoice->update(['status' => InvoiceStatus::Sent]);
            }

            $payment = Payment::create([
                'tenant_id' => $invoice->tenant_id,
                'user_id' => $invoice->user_id,
                'order_id' => $invoice->order_id,
                'invoice_id' => $invoice->id,
                'gateway' => $paymentMethod,
                'transaction_id' => $transactionId,
                'amount' => $payAmount,
                'status' => PaymentStatus::Completed,
                'gateway_response' => array_filter([
                    'source' => 'admin_manual',
                    'payment_method' => $paymentMethod,
                    'reference' => $reference,
                    'notes' => $notes,
                    'recorded_at' => now()->toIso8601String(),
                    'invoice_total' => $invoiceTotal,
                    'previously_paid' => $alreadyPaid,
                ]),
            ]);

            $newRemaining = round($remaining - $payAmount, 2);
            if ($newRemaining > 0) {
                Payment::create([
                    'tenant_id' => $invoice->tenant_id,
                    'user_id' => $invoice->user_id,
                    'order_id' => $invoice->order_id,
                    'invoice_id' => $invoice->id,
                    'gateway' => 'manual',
                    'transaction_id' => 'PENDING-'.$invoice->invoice_number.'-'.time(),
                    'amount' => $newRemaining,
                    'status' => PaymentStatus::Pending,
                    'gateway_response' => [
                        'source' => 'admin_partial_balance',
                        'recorded_at' => now()->toIso8601String(),
                    ],
                ]);
            }

            return [
                'payment' => $payment->fresh(['user', 'order', 'invoice']),
                'invoice' => $invoice->fresh(['user', 'order']),
                'amount_due' => $newRemaining,
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

    /**
     * For fully paid invoices, run the same fulfillment flow used by webhooks/admin invoice-paid.
     * This ensures renewal invoices extend subscription dates/status correctly.
     */
    protected function finalizeSubscriptionForPaidInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('order');

        if ($invoice->order) {
            app(PurchaseService::class)->fulfillPaidOrder($invoice->order->fresh(['invoice']));

            return;
        }

        // Fallback for legacy invoices without order linkage.
        $this->activateSubscriptionForInvoice($invoice);
    }

    public function resolveInvoiceForManualPayment(?string $invoiceId, ?string $orderId, ?string $subscriptionId = null): Invoice
    {
        if ($subscriptionId) {
            $invoiceBySubscription = Invoice::withoutGlobalScopes()
                ->where('subscription_id', $subscriptionId)
                ->latest('id')
                ->first();

            if ($invoiceBySubscription) {
                return $invoiceBySubscription;
            }

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

            $this->forgetSubscriptionDomainSettings($subscription);

            $invoices = Invoice::withoutGlobalScopes()
                ->where('subscription_id', $subscription->id)
                ->get();

            $orderIds = $invoices
                ->pluck('order_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            foreach ($invoices as $invoice) {
                $this->deleteInvoice($invoice);
            }

            foreach ($orderIds as $orderId) {
                $order = Order::withoutGlobalScopes()->find($orderId);
                if ($order) {
                    $this->deleteOrder($order);
                }
            }

            LicenseKey::withTrashed()
                ->where('subscription_id', $subscription->id)
                ->get()
                ->each(fn (LicenseKey $license) => $this->permanentlyDelete($license));

            $this->permanentlyDelete($subscription);
        });
    }

    protected function forgetSubscriptionDomainSettings(Subscription $subscription): void
    {
        if (! $subscription->tenant_id) {
            return;
        }

        $tenant = Tenant::withoutGlobalScopes()->find($subscription->tenant_id);
        if (! $tenant) {
            return;
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $key = (string) $subscription->id;
        $changed = false;

        foreach (['subscription_domains', 'pending_subscription_domains', 'subscription_domain_skips'] as $bucket) {
            if (! is_array($settings[$bucket] ?? null)) {
                continue;
            }

            if (array_key_exists($key, $settings[$bucket]) || array_key_exists((int) $subscription->id, $settings[$bucket])) {
                unset($settings[$bucket][$key], $settings[$bucket][(int) $subscription->id]);
                $changed = true;
            }
        }

        if ($changed) {
            $tenant->update(['settings' => $settings]);
        }
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
