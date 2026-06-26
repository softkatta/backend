<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;

class PayuGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'payu';
    }

    public function initiatePayment(Order $order, array $payload = []): array
    {
        return $this->stubResponse($order, 'initiate');
    }

    public function verifyPayment(Payment $payment, array $payload = []): bool
    {
        return ($payload['status'] ?? null) === 'success';
    }

    public function refund(Payment $payment, array $payload = []): array
    {
        return [
            'gateway' => $this->getName(),
            'refund_id' => 'PAYU-REF-'.uniqid(),
            'status' => 'success',
        ];
    }
}
