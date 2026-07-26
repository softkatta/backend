<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $user = App\Models\User::where('role', 'client')->first();
    $product = App\Models\Product::where('slug', 'study-point-management-software')->first();
    $plan = $product?->plans()->where('is_active', true)->orderBy('sort_order')->first();

    if (! $user || ! $product || ! $plan) {
        echo "Missing test data\n";
        echo 'User: ' . ($user ? 'yes' : 'no') . "\n";
        echo 'Product: ' . ($product ? 'yes' : 'no') . "\n";
        echo 'Plan: ' . ($plan ? 'yes' : 'no') . "\n";
        exit(0);
    }

    echo "User id={$user->id}, tenant_id={$user->tenant_id}, email={$user->email}\n";
    echo "Plan id={$plan->id}, product_id={$plan->product_id}, trial_days={$plan->trial_days}\n";

    $purchase = app(App\Services\PurchaseService::class)->startTrialForExistingUser($user, $product, $plan);
    print_r($purchase);
} catch (Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    if ($e->getPrevious()) {
        echo 'Previous: ' . get_class($e->getPrevious()) . ': ' . $e->getPrevious()->getMessage() . "\n";
    }
}
