<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    protected function stubResponse(Order $order, string $action, array $payload = []): array
    {
        $amount = (float) ($payload['amount'] ?? $order->total_amount);

        return [
            'gateway' => $this->getName(),
            'action' => $action,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => $amount,
            'amount_paise' => (int) round($amount * 100),
            'transaction_id' => Str::upper($this->getName()).'_'.Str::random(16),
            'razorpay_order_id' => 'order_stub_'.Str::random(10),
            'razorpay_key_id' => null,
            'stub' => true,
            'status' => 'pending',
            'message' => 'Payment gateway not configured — using stub mode. Configure Razorpay in Admin → Settings → Integrations.',
        ];
    }
}
