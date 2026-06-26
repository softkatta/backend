<?php

use App\Services\PaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:sync-from-invoices', function (PaymentService $payments) {
    $created = $payments->syncFromPaidInvoices();
    $this->info("Created {$created} payment record(s) from paid invoices.");
})->purpose('Create missing payment records for paid invoices');
