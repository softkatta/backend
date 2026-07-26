<?php

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Subscription::query()
            ->with('licenseKey')
            ->where('status', SubscriptionStatus::Trial)
            ->whereNotNull('trial_ends_at')
            ->eachById(function (Subscription $subscription): void {
                $license = $subscription->licenseKey;

                if (! $license) {
                    return;
                }

                $meta = is_array($license->meta) ? $license->meta : [];

                $license->update([
                    'status' => LicenseStatus::Active,
                    'is_product_active' => true,
                    'expires_at' => $subscription->trial_ends_at,
                    'meta' => array_merge($meta, ['temporary_trial' => true]),
                ]);
            });
    }

    public function down(): void
    {
        // Trial licenses are intentionally not converted back to paid licenses.
    }
};
