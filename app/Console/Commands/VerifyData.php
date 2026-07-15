<?php

namespace App\Console\Commands;

use App\Models\LicenseKey;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;

class VerifyData extends Command
{
    protected $signature = 'verify:data';

    protected $description = 'Verify sample data in database';

    public function handle(): int
    {
        $this->info('=== PRODUCTS ===');
        foreach (Product::all() as $p) {
            $this->line($p->id . '. ' . $p->name);
        }

        $this->newLine();
        $this->info('=== TOTAL PLANS ===');
        $this->line('Total plans: ' . Plan::count());

        $this->newLine();
        $this->info('=== PLANS PER PRODUCT ===');
        foreach (Product::all() as $p) {
            $this->line($p->name . ': ' . $p->plans->count() . ' plans');
        }

        $this->newLine();
        $this->info('=== SAMPLE CUSTOMERS (Client Users) ===');
        foreach (User::where('role', 'client')->get() as $u) {
            $this->line($u->id . '. ' . $u->name . ' (' . $u->email . ') - ' . $u->company_name);
        }

        $this->newLine();
        $this->info('=== LICENSE KEYS ===');
        foreach (LicenseKey::with('user', 'product')->get() as $lk) {
            $this->line($lk->license_key . ' - ' . $lk->user->name . ' - ' . $lk->product->name . ' - ' . $lk->status->value);
        }

        $this->newLine();
        $this->info('✅ Database populated successfully!');

        return self::SUCCESS;
    }
}
