<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;

class CashfreeGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'cashfree';
    }

    public function initiatePayment(Order $order, array $payload = []): array
    {
        return array_merge($this->stubResponse($order, 'initiate'), [
            'payment_session_id' => 'session_'.uniqid(),
        ]);
    }

    public function verifyPayment(Payment $payment, array $payload = []): bool
    {
        return in_array($payload['payment_status'] ?? '', ['SUCCESS', 'PAID'], true);
    }

    public function refund(Payment $payment, array $payload = []): array
    {
        return [
            'gateway' => $this->getName(),
            'refund_id' => 'cf_refund_'.uniqid(),
            'status' => 'SUCCESS',
        ];
    }
}
