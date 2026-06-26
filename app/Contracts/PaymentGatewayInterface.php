<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function getName(): string;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function initiatePayment(Order $order, array $payload = []): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyPayment(Payment $payment, array $payload = []): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refund(Payment $payment, array $payload = []): array;
}
