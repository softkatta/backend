<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionRenewalService
{
    public const PURPOSE_RENEWAL = 'renewal';

    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceProfileService $invoiceProfile,
        private readonly NotificationService $notifications,
        private readonly LicenseService $licenseService,
    ) {}

    /**
     * For auto-renew subscriptions nearing expiry: create a renewal invoice and
     * notify the customer. Does NOT extend ends_at — that happens only after payment.
     */
    public function createPendingRenewals(int $withinDays = 7): int
    {
        $from = now();
        $to = now()->addDays($withinDays);
        $created = 0;

        Subscription::query()
            ->with(['user', 'product', 'plan'])
            ->where('auto_renew', true)
            ->whereNull('cancelled_at')
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trial,
                SubscriptionStatus::ExpiringSoon,
            ])
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$from, $to])
            ->orderBy('id')
            ->eachById(function (Subscription $subscription) use (&$created): void {
                if ($this->hasOpenRenewalInvoice($subscription)) {
                    return;
                }

                $plan = $subscription->plan;
                if (! $plan || $plan->billing_cycle->months() === null) {
                    return;
                }

                if ((float) $plan->price <= 0) {
                    return;
                }

                try {
                    $this->createRenewalInvoice($subscription);
                    $created++;
                } catch (\Throwable $e) {
                    Log::error('SoftKatta renewal invoice failed', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return $created;
    }

    /**
     * Extend subscription + license only after a renewal invoice is paid.
     */
    public function applyPaidRenewal(Subscription $subscription, ?Invoice $invoice = null): Subscription
    {
        if ($invoice) {
            $details = $invoice->billing_details ?? [];
            if (! empty($details['renewal_applied_at'])) {
                return $subscription->fresh(['plan', 'product', 'licenseKey']);
            }
        }

        $subscription->loadMissing(['plan', 'product', 'user', 'licenseKey']);

        $plan = $subscription->plan;
        $months = $plan?->billing_cycle?->months();

        $base = ($subscription->ends_at && $subscription->ends_at->isFuture())
            ? $subscription->ends_at->copy()
            : now();

        $newEndsAt = $months !== null
            ? $base->copy()->addMonths($months)
            : null;

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'ends_at' => $newEndsAt,
            'cancelled_at' => null,
        ]);

        $license = $subscription->licenseKey;
        if ($license) {
            $license->update([
                'status' => LicenseStatus::Active,
                'is_product_active' => true,
                'expires_at' => $newEndsAt,
            ]);
        } else {
            try {
                $this->licenseService->generateForSubscription($subscription->fresh());
            } catch (\App\Exceptions\TenantDomainsRequiredException) {
                // Renewal payment succeeded; license waits until SoftKatta Admin domains are assigned.
            }
        }

        if ($invoice) {
            $invoice->update([
                'billing_details' => array_merge($invoice->billing_details ?? [], [
                    'renewal_applied_at' => now()->toIso8601String(),
                ]),
            ]);
        }

        $product = $subscription->product?->name ?? 'your SoftKatta product';
        $planName = $plan?->name ?? 'plan';
        $ends = $newEndsAt?->timezone(config('app.timezone'))->format('d M Y') ?? 'lifetime';
        $invoiceNo = $invoice?->invoice_number ?: null;

        $this->notify(
            $subscription->user,
            'subscription_renewed',
            'SoftKatta subscription renewed',
            "Hi {$this->firstName($subscription->user)}, payment received. Your {$product} ({$planName}) subscription is renewed until {$ends}.",
            [
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoiceNo,
            ],
            array_filter([
                'Product' => $product,
                'Plan' => $planName,
                'Valid until' => $ends,
                'Invoice' => $invoiceNo,
            ]),
        );

        return $subscription->fresh(['plan', 'product', 'licenseKey']);
    }

    public function isRenewalInvoice(?Invoice $invoice): bool
    {
        if (! $invoice) {
            return false;
        }

        $details = $invoice->billing_details ?? [];

        return ($details['purpose'] ?? null) === self::PURPOSE_RENEWAL;
    }

    public function hasOpenRenewalInvoice(Subscription $subscription): bool
    {
        return Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', [
                InvoiceStatus::Draft,
                InvoiceStatus::Sent,
                InvoiceStatus::Overdue,
            ])
            ->where('billing_details->purpose', self::PURPOSE_RENEWAL)
            ->exists();
    }

    public function cancelOpenRenewalInvoices(Subscription $subscription): int
    {
        $count = 0;

        Invoice::query()
            ->with('order')
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', [
                InvoiceStatus::Draft,
                InvoiceStatus::Sent,
                InvoiceStatus::Overdue,
            ])
            ->where('billing_details->purpose', self::PURPOSE_RENEWAL)
            ->orderBy('id')
            ->eachById(function (Invoice $invoice) use (&$count): void {
                $invoice->update(['status' => InvoiceStatus::Cancelled]);
                if ($invoice->order && $invoice->order->status === 'pending') {
                    $invoice->order->update(['status' => 'cancelled']);
                }
                $count++;
            });

        return $count;
    }

    /**
     * @return array{order: Order, invoice: Invoice}
     */
    public function createRenewalInvoice(Subscription $subscription): array
    {
        return DB::transaction(function () use ($subscription) {
            $subscription->loadMissing(['user', 'product', 'plan']);
            $user = $subscription->user;
            $product = $subscription->product;
            $plan = $subscription->plan;

            $amount = (float) $plan->price;
            $taxRate = $this->invoiceProfile->gstRate();
            $taxAmount = round($amount * ($taxRate / 100), 2);
            $totalAmount = $amount + $taxAmount;

            $order = Order::create([
                'tenant_id' => $subscription->tenant_id,
                'user_id' => $subscription->user_id,
                'product_id' => $subscription->product_id,
                'plan_id' => $subscription->plan_id,
                'order_number' => 'SK-REN-'.strtoupper(Str::random(10)),
                'amount' => $amount,
                'discount_amount' => 0,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_gateway' => 'razorpay',
            ]);

            $ends = $subscription->ends_at?->timezone(config('app.timezone'))->format('d M Y') ?? 'soon';

            $invoice = $this->invoiceService->generateFromOrder($order, $subscription, [
                'item_description' => "Renewal — {$product->name} — {$plan->name} ({$plan->billing_cycle->label()})",
                'billing_details' => [
                    'purpose' => self::PURPOSE_RENEWAL,
                    'renewal_for_subscription_id' => $subscription->id,
                    'period_ends_at' => $subscription->ends_at?->toIso8601String(),
                ],
                'due_date' => $subscription->ends_at?->toDateString() ?? now()->addDays(7)->toDateString(),
            ]);

            $invoice->update(['status' => InvoiceStatus::Sent]);

            $amountLabel = number_format($totalAmount, 2);

            $this->notify(
                $user,
                'subscription_renewal_payment_due',
                'Action required: pay to renew SoftKatta subscription',
                "Hi {$this->firstName($user)}, auto-renew is enabled for {$product->name} ({$plan->name}). Pay ₹{$amountLabel} (invoice {$invoice->invoice_number}) before {$ends} to renew. Without payment, the subscription will not renew and will expire.",
                [
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'order_id' => $order->id,
                    'amount' => $totalAmount,
                ],
                [
                    'Product' => $product->name,
                    'Plan' => $plan->name,
                    'Invoice' => $invoice->invoice_number,
                    'Amount due' => '₹'.$amountLabel,
                    'Pay before' => $ends,
                    'Action' => 'Complete payment from your SoftKatta dashboard (Invoices). Auto-renew does not charge without payment confirmation.',
                ],
            );

            return [
                'order' => $order->fresh(),
                'invoice' => $invoice->fresh('items'),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $emailDetails
     */
    private function notify(
        ?User $user,
        string $type,
        string $title,
        string $message,
        array $data = [],
        array $emailDetails = [],
    ): void {
        if (! $user) {
            return;
        }

        try {
            $this->notifications->send(
                $user,
                $type,
                $title,
                $message,
                NotificationService::allChannels(),
                $data,
                array_filter($emailDetails, fn ($v) => $v !== null && $v !== ''),
            );
        } catch (\Throwable $e) {
            Log::warning('SoftKatta renewal notify failed', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function firstName(?User $user): string
    {
        if (! $user?->name) {
            return 'there';
        }

        return explode(' ', trim($user->name))[0] ?: 'there';
    }
}
