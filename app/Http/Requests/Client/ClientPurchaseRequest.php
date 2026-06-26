<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientPurchaseRequest extends FormRequest
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
            'product_id' => ['required', 'exists:products,id'],
            'plan_id' => [
                'required',
                Rule::exists('plans', 'id')->where(
                    fn ($query) => $query->where('product_id', $this->input('product_id'))
                ),
            ],
            'payment_gateway' => ['nullable', 'string', 'in:razorpay,stripe,payu,cashfree'],
        ];
    }
}
