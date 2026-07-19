<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Payment;
use App\Services\InvoiceService;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends BaseApiController
{
    public function __construct(
        private readonly PurchaseService $purchaseService,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * POST /api/v1/webhooks/razorpay
     *
     * Razorpay sends webhook events here. Signature is verified before any
     * processing to prevent spoofed requests.
     */
    public function razorpay(Request $request): JsonResponse
    {
        $secret = config('services.razorpay.webhook_secret');
        $signature = $request->header('X-Razorpay-Signature', '');
        $payload = $request->getContent();

        if (! $this->verifyRazorpaySignature($payload, $signature, $secret)) {
            Log::warning('Razorpay webhook signature mismatch', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $event = $request->input('event');
        $data = $request->input('payload', []);

        match ($event) {
            'payment.captured' => $this->handlePaymentCaptured($data),
            'payment.failed' => $this->handlePaymentFailed($data),
            'subscription.charged' => $this->handleSubscriptionCharged($data),
            default => Log::info("Razorpay webhook: unhandled event [{$event}]"),
        };

        return response()->json(['status' => 'ok']);
    }

    private function handlePaymentCaptured(array $data): void
    {
        $paymentEntity = $data['payment']['entity'] ?? [];
        $razorpayId = $paymentEntity['id'] ?? null;

        if (! $razorpayId) {
            return;
        }

        $payment = Payment::where('transaction_id', $razorpayId)->first();

        if (! $payment) {
            Log::warning("Razorpay webhook: payment not found [{$razorpayId}]");

            return;
        }

        if ($payment->status === PaymentStatus::Completed) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::Completed,
            'gateway_response' => $paymentEntity,
        ]);

        $order = $payment->order;

        if (! $order) {
            return;
        }

        $order->update([
            'status' => 'completed',
            'payment_id' => $razorpayId,
        ]);

        if ($order->invoice) {
            $this->invoiceService->markAsPaid($order->invoice);
            $order->setRelation('invoice', $order->invoice->fresh());
        }

        // First purchase activation OR renewal extension — only after payment.
        $this->purchaseService->fulfillPaidOrder($order->fresh(['invoice']));
    }

    private function handlePaymentFailed(array $data): void
    {
        $paymentEntity = $data['payment']['entity'] ?? [];
        $razorpayId = $paymentEntity['id'] ?? null;

        if (! $razorpayId) {
            return;
        }

        Payment::where('transaction_id', $razorpayId)
            ->update([
                'status' => PaymentStatus::Failed,
                'gateway_response' => $paymentEntity,
            ]);
    }

    private function handleSubscriptionCharged(array $data): void
    {
        // SoftKatta does not auto-extend on Razorpay subscription.charged alone.
        // Renewal requires SoftKatta renewal invoice + verified payment capture.
        $paymentEntity = $data['payment']['entity'] ?? [];
        $razorpayId = $paymentEntity['id'] ?? null;

        if ($razorpayId) {
            Log::info('Razorpay subscription.charged ignored for auto-extend; awaiting SoftKatta paid renewal invoice', [
                'razorpay_payment_id' => $razorpayId,
            ]);
        }
    }

    private function verifyRazorpaySignature(string $payload, string $signature, ?string $secret): bool
    {
        if (empty($secret)) {
            return app()->isLocal() || app()->runningUnitTests();
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
