<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Client\VerifyPaymentRequest;
use App\Models\Payment;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;

class PaymentController extends BaseApiController
{
    public function verify(VerifyPaymentRequest $request, PurchaseService $purchaseService): JsonResponse
    {
        $payment = Payment::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->integer('payment_id'));

        $result = $purchaseService->completePayment($payment, $request->validated());

        return $this->success($result, 'Payment verified successfully.');
    }
}
