<?php

namespace App\Console\Commands;

use App\Enums\LicenseStatus;
use App\Models\LicenseKey;
use App\Services\LicenseService;
use Illuminate\Console\Command;

class ExpireLicenses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'licenses:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire licenses whose expires_at is in the past';

    public function handle(LicenseService $licenseService): int
    {
        $this->info('Checking for expired licenses...');

        $expired = LicenseKey::query()
            ->where('status', LicenseStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $this->info('Found: '.$expired->count());

        foreach ($expired as $license) {
            try {
                $licenseService->markExpired($license);
                $this->info('Expired: '.$license->license_key);
            } catch (\Throwable $e) {
                $this->error('Failed to expire: '.$license->license_key.' — '.$e->getMessage());
            }
        }

        return 0;
    }
}
