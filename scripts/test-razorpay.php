<?php

use App\Models\Order;
use App\Services\Payment\RazorpayGateway;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$order = Order::withoutGlobalScopes()->latest('id')->first();
$gateway = new RazorpayGateway;
$result = $gateway->initiatePayment($order);

echo json_encode([
    'stub' => $result['stub'] ?? false,
    'razorpay_key_id' => $result['razorpay_key_id'] ?? null,
    'razorpay_order_id' => $result['razorpay_order_id'] ?? null,
], JSON_PRETTY_PRINT);
