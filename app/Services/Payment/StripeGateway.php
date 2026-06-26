<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;

class StripeGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'stripe';
    }

    public function initiatePayment(Order $order, array $payload = []): array
    {
        return array_merge($this->stubResponse($order, 'initiate'), [
            'client_secret' => 'pi_'.uniqid().'_secret_'.uniqid(),
        ]);
    }

    public function verifyPayment(Payment $payment, array $payload = []): bool
    {
        return ($payload['status'] ?? null) === 'succeeded';
    }

    public function refund(Payment $payment, array $payload = []): array
    {
        return [
            'gateway' => $this->getName(),
            'refund_id' => 're_'.uniqid(),
            'status' => 'succeeded',
        ];
    }
}
