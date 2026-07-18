<?php

namespace App\Console\Commands;

use App\Services\AutomationService;
use App\Services\PaymentService;
use Illuminate\Console\Command;

class SoftkattaAutomate extends Command
{
    protected $signature = 'softkatta:automate
                            {--task=all : all|subscriptions|licenses|invoices|payments|cleanup}';

    protected $description = 'Run SoftKatta platform automations (subscriptions, licenses, invoices, cleanup)';

    public function handle(AutomationService $automation, PaymentService $payments): int
    {
        $task = strtolower((string) $this->option('task'));

        $summary = match ($task) {
            'all' => $automation->runAll(),
            'subscriptions' => [
                'subscriptions_expiring_soon' => $automation->markSubscriptionsExpiringSoon(),
                'subscriptions_expired' => $automation->expireSubscriptions(),
            ],
            'licenses' => [
                'licenses_expired' => $automation->expireLicenses(),
            ],
            'invoices' => [
                'invoices_overdue' => $automation->markInvoicesOverdue(),
            ],
            'payments' => [
                'payments_synced' => $payments->syncFromPaidInvoices(),
            ],
            'cleanup' => [
                'refresh_tokens_pruned' => $automation->pruneExpiredRefreshTokens(),
                'site_visits_pruned' => $automation->pruneOldSiteVisits(),
            ],
            default => null,
        };

        if ($summary === null) {
            $this->error("Unknown task [{$task}]. Use: all, subscriptions, licenses, invoices, payments, cleanup");

            return self::FAILURE;
        }

        foreach ($summary as $key => $count) {
            $this->line(sprintf('%s: %d', $key, $count));
        }

        $this->info('Automation finished.');

        return self::SUCCESS;
    }
}
