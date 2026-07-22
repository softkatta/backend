<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use RuntimeException;

class StripeGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'stripe';
    }

    public function initiatePayment(Order $order, array $payload = []): array
    {
        throw new RuntimeException('Stripe is not configured. SoftKatta Admin → Settings → Integrations.');
    }

    public function verifyPayment(Payment $payment, array $payload = []): bool
    {
        return false;
    }

    public function refund(Payment $payment, array $payload = []): array
    {
        throw new RuntimeException('Stripe refunds are not available until the gateway is configured.');
    }
}
