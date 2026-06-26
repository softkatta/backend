<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$order = App\Models\Order::withoutGlobalScopes()->latest('id')->first();
$gateway = new App\Services\Payment\RazorpayGateway();
$result = $gateway->initiatePayment($order);

echo json_encode([
    'stub' => $result['stub'] ?? false,
    'razorpay_key_id' => $result['razorpay_key_id'] ?? null,
    'razorpay_order_id' => $result['razorpay_order_id'] ?? null,
], JSON_PRETTY_PRINT);
