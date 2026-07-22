<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use RuntimeException;

class CashfreeGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'cashfree';
    }

    public function initiatePayment(Order $order, array $payload = []): array
    {
        throw new RuntimeException('Cashfree is not configured. SoftKatta Admin → Settings → Integrations.');
    }

    public function verifyPayment(Payment $payment, array $payload = []): bool
    {
        return false;
    }

    public function refund(Payment $payment, array $payload = []): array
    {
        throw new RuntimeException('Cashfree refunds are not available until the gateway is configured.');
    }
}
