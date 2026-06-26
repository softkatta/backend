<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Services\IntegrationCredentialService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'razorpay';
    }

    public function initiatePayment(Order $order, array $payload = []): array
    {
        $creds = app(IntegrationCredentialService::class)->razorpay();

        if (! $creds) {
            return $this->stubResponse($order, 'initiate');
        }

        $amountPaise = (int) round((float) $order->total_amount * 100);

        $client = Http::withBasicAuth($creds['key_id'], $creds['key_secret']);

        if (! config('services.razorpay.verify_ssl', true)) {
            $client = $client->withoutVerifying();
        }

        $response = $client->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $order->order_number,
                'notes' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Razorpay order creation failed: '.$response->body());
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return [
            'gateway' => $this->getName(),
            'razorpay_order_id' => $data['id'] ?? null,
            'razorpay_key_id' => $creds['key_id'],
            'amount' => (float) $order->total_amount,
            'amount_paise' => $amountPaise,
            'currency' => 'INR',
            'transaction_id' => $data['id'] ?? null,
            'status' => 'created',
        ];
    }

    public function verifyPayment(Payment $payment, array $payload = []): bool
    {
        $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');
        $orderId = (string) ($payload['razorpay_order_id'] ?? '');
        $signature = (string) ($payload['razorpay_signature'] ?? '');

        if ($paymentId === '' || $orderId === '' || $signature === '') {
            return false;
        }

        $creds = app(IntegrationCredentialService::class)->razorpay();

        if (! $creds) {
            return $paymentId !== '';
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $creds['key_secret']);

        return hash_equals($expected, $signature);
    }

    public function refund(Payment $payment, array $payload = []): array
    {
        return [
            'gateway' => $this->getName(),
            'refund_id' => 'rfnd_'.uniqid(),
            'status' => 'processed',
        ];
    }
}
