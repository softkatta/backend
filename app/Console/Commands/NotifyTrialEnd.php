<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\LicenseService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyTrialEnd extends Command
{
    protected $signature = 'subscriptions:notify-trial-end';

    protected $description = 'Notify users when their free trial ends';

    public function handle(NotificationService $notifier, LicenseService $licenses): int
    {
        $now = now();

        $subscriptions = Subscription::with(['user', 'product', 'licenseKey'])
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->whereNull('trial_notification_sent_at')
            ->get();

        foreach ($subscriptions as $sub) {
            try {
                $sub->update(['status' => SubscriptionStatus::Expired->value]);

                if ($sub->licenseKey) {
                    $licenses->markExpired($sub->licenseKey);
                }

                $user = $sub->user;
                $product = $sub->product;
                $message = "Your free trial for {$product->name} has ended. Please choose a plan to continue using the product without interruption.";

                $notifier->send(
                    $user,
                    'trial_ended',
                    'Your free trial ended',
                    $message,
                    NotificationService::allChannels(),
                    ['product_id' => $product->id, 'subscription_id' => $sub->id],
                    ['product_name' => $product->name]
                );

                $sub->update(['trial_notification_sent_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('Failed to notify trial end', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info('Notified '.$subscriptions->count().' subscriptions.');

        return 0;
    }
}
