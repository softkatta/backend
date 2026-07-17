<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientBatchPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.plan_id' => ['required', 'exists:plans,id'],
            'payment_gateway' => ['nullable', 'string', 'in:razorpay,stripe,payu,cashfree'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $planId = $item['plan_id'] ?? null;

                if (! $productId || ! $planId) {
                    continue;
                }

                $exists = \App\Models\Plan::query()
                    ->where('id', $planId)
                    ->where('product_id', $productId)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add("items.{$index}.plan_id", 'The selected plan does not belong to this product.');
                }
            }
        });
    }
}
