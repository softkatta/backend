<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\LicenseKey;
use App\Models\SiteVisit;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserRefreshToken;
use Illuminate\Support\Facades\Log;

class AutomationService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Run all platform automations and return a summary.
     *
     * @return array<string, int>
     */
    public function runAll(): array
    {
        $summary = [
            'subscriptions_expiring_soon' => $this->markSubscriptionsExpiringSoon(),
            'subscriptions_expired' => $this->expireSubscriptions(),
            'licenses_expired' => $this->expireLicenses(),
            'invoices_overdue' => $this->markInvoicesOverdue(),
            'payments_synced' => $this->payments->syncFromPaidInvoices(),
            'refresh_tokens_pruned' => $this->pruneExpiredRefreshTokens(),
            'site_visits_pruned' => $this->pruneOldSiteVisits(),
            'customer_notifications_sent' => $this->notificationsSent,
        ];

        Log::info('SoftKatta automation completed', $summary);

        return $summary;
    }

    private int $notificationsSent = 0;

    public function markSubscriptionsExpiringSoon(int $withinDays = 7): int
    {
        $from = now();
        $to = now()->addDays($withinDays);
        $count = 0;

        Subscription::query()
            ->with(['user', 'product', 'plan'])
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial])
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$from, $to])
            ->orderBy('id')
            ->eachById(function (Subscription $subscription) use (&$count): void {
                $subscription->update(['status' => SubscriptionStatus::ExpiringSoon]);
                $count++;

                $product = $subscription->product?->name ?? 'your SoftKatta product';
                $plan = $subscription->plan?->name ?? 'plan';
                $ends = $subscription->ends_at?->timezone(config('app.timezone'))->format('d M Y') ?? 'soon';

                $this->notifyCustomer(
                    $subscription->user,
                    'subscription_expiring',
                    'Your SoftKatta subscription is expiring soon',
                    "Hi {$this->firstName($subscription->user)}, your subscription to {$product} ({$plan}) expires on {$ends}. Renew now to keep uninterrupted access.",
                    [
                        'subscription_id' => $subscription->id,
                        'product_id' => $subscription->product_id,
                        'plan_id' => $subscription->plan_id,
                    ],
                    [
                        'Product' => $product,
                        'Plan' => $plan,
                        'Expires on' => $ends,
                        'Action' => 'Please renew from your SoftKatta dashboard.',
                    ],
                );
            });

        return $count;
    }

    public function expireSubscriptions(): int
    {
        $count = 0;

        Subscription::query()
            ->with(['user', 'product', 'plan'])
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trial,
                SubscriptionStatus::ExpiringSoon,
            ])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->orderBy('id')
            ->eachById(function (Subscription $subscription) use (&$count): void {
                $subscription->update(['status' => SubscriptionStatus::Expired]);
                $count++;

                $product = $subscription->product?->name ?? 'your SoftKatta product';
                $plan = $subscription->plan?->name ?? 'plan';
                $ended = $subscription->ends_at?->timezone(config('app.timezone'))->format('d M Y') ?? 'today';

                $this->notifyCustomer(
                    $subscription->user,
                    'subscription_expired',
                    'Your SoftKatta subscription has expired',
                    "Hi {$this->firstName($subscription->user)}, your subscription to {$product} ({$plan}) expired on {$ended}. Renew to restore access to your product.",
                    [
                        'subscription_id' => $subscription->id,
                        'product_id' => $subscription->product_id,
                        'plan_id' => $subscription->plan_id,
                    ],
                    [
                        'Product' => $product,
                        'Plan' => $plan,
                        'Expired on' => $ended,
                        'Action' => 'Renew from your SoftKatta dashboard to restore access.',
                    ],
                );
            });

        return $count;
    }

    public function expireLicenses(): int
    {
        $count = 0;

        LicenseKey::query()
            ->with(['user', 'product'])
            ->where('status', LicenseStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->eachById(function (LicenseKey $license) use (&$count): void {
                $license->update(['status' => LicenseStatus::Expired]);
                $count++;

                $product = $license->product?->name ?? 'your SoftKatta product';
                $ended = $license->expires_at?->timezone(config('app.timezone'))->format('d M Y') ?? 'today';

                $this->notifyCustomer(
                    $license->user,
                    'license_expired',
                    'Your SoftKatta license has expired',
                    "Hi {$this->firstName($license->user)}, your license for {$product} expired on {$ended}. Renew your subscription to reactivate the license.",
                    [
                        'license_id' => $license->id,
                        'product_id' => $license->product_id,
                    ],
                    [
                        'Product' => $product,
                        'License' => $license->license_key,
                        'Expired on' => $ended,
                    ],
                );
            });

        return $count;
    }

    public function markInvoicesOverdue(): int
    {
        $count = 0;

        Invoice::withoutGlobalScopes()
            ->with('user')
            ->where('status', InvoiceStatus::Sent)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('id')
            ->eachById(function (Invoice $invoice) use (&$count): void {
                $invoice->update(['status' => InvoiceStatus::Overdue]);
                $count++;

                $number = $invoice->invoice_number ?: ('#'.$invoice->id);
                $amount = number_format((float) $invoice->total_amount, 2);
                $due = $invoice->due_date?->format('d M Y') ?? 'earlier';

                $this->notifyCustomer(
                    $invoice->user,
                    'invoice_overdue',
                    'SoftKatta invoice payment overdue',
                    "Hi {$this->firstName($invoice->user)}, invoice {$number} for ₹{$amount} was due on {$due} and is now overdue. Please complete payment at the earliest.",
                    [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                    ],
                    [
                        'Invoice' => $number,
                        'Amount' => '₹'.$amount,
                        'Due date' => $due,
                        'Status' => 'Overdue',
                    ],
                );
            });

        return $count;
    }

    public function pruneExpiredRefreshTokens(): int
    {
        return UserRefreshToken::query()
            ->where('expires_at', '<', now())
            ->delete();
    }

    public function pruneOldSiteVisits(int $keepDays = 90): int
    {
        return SiteVisit::query()
            ->where('visited_on', '<', now()->subDays($keepDays)->toDateString())
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $emailDetails
     */
    private function notifyCustomer(
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
                $emailDetails,
            );
            $this->notificationsSent++;
        } catch (\Throwable $e) {
            Log::warning('SoftKatta automation customer notify failed', [
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
