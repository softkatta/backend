<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\PurchaseService;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var PurchaseService $purchase */
$purchase = app(PurchaseService::class);

$fixed = 0;
$skippedNoOrder = 0;
$alreadyApplied = 0;
$markedLegacyRenewal = 0;

/**
 * Phase 1: canonical paid renewal invoices (purpose=renewal).
 */
Invoice::withoutGlobalScopes()
    ->with(['order', 'subscription'])
    ->where('status', 'paid')
    ->where('billing_details->purpose', 'renewal')
    ->orderBy('id')
    ->chunkById(100, function ($invoices) use ($purchase, &$fixed, &$skippedNoOrder, &$alreadyApplied): void {
        foreach ($invoices as $invoice) {
            $details = is_array($invoice->billing_details) ? $invoice->billing_details : [];

            if (!empty($details['renewal_applied_at'])) {
                $alreadyApplied++;
                continue;
            }

            if (!$invoice->order) {
                $skippedNoOrder++;
                continue;
            }

            $purchase->fulfillPaidOrder($invoice->order->fresh(['invoice']));
            $fixed++;
        }
    });

/**
 * Phase 2: legacy renewals where purpose flag is missing but invoice item says Renewal.
 */
Invoice::withoutGlobalScopes()
    ->with(['order', 'subscription', 'items'])
    ->where('status', 'paid')
    ->where(function ($q): void {
        $q->whereNull('billing_details->purpose')
            ->orWhere('billing_details->purpose', '!=', 'renewal');
    })
    ->whereHas('items', function ($q): void {
        $q->where('description', 'like', 'Renewal%');
    })
    ->orderBy('id')
    ->chunkById(100, function ($invoices) use ($purchase, &$fixed, &$skippedNoOrder, &$alreadyApplied, &$markedLegacyRenewal): void {
        foreach ($invoices as $invoice) {
            $details = is_array($invoice->billing_details) ? $invoice->billing_details : [];

            if (!empty($details['renewal_applied_at'])) {
                $alreadyApplied++;
                continue;
            }

            if (!$invoice->order || !$invoice->subscription) {
                $skippedNoOrder++;
                continue;
            }

            $invoice->update([
                'billing_details' => array_merge($details, [
                    'purpose' => 'renewal',
                    'renewal_for_subscription_id' => $invoice->subscription_id,
                    'period_ends_at' => $invoice->subscription->ends_at?->toIso8601String(),
                ]),
            ]);
            $markedLegacyRenewal++;

            $purchase->fulfillPaidOrder($invoice->order->fresh(['invoice']));
            $fixed++;
        }
    });

echo "fixed={$fixed}\n";
echo "already_applied={$alreadyApplied}\n";
echo "skipped_no_order={$skippedNoOrder}\n";
echo "marked_legacy_renewal={$markedLegacyRenewal}\n";
