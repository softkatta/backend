<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RecordManualPaymentRequest extends FormRequest
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
            'invoice_id' => ['nullable', 'exists:invoices,id', 'required_without_all:order_id,subscription_id,payment_id'],
            'order_id' => ['nullable', 'exists:orders,id', 'required_without_all:invoice_id,subscription_id,payment_id'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id', 'required_without_all:invoice_id,order_id,payment_id'],
            'payment_id' => ['nullable', 'exists:payments,id', 'required_without_all:invoice_id,order_id,subscription_id'],
            'payment_method' => ['required', 'string', 'in:cash,cheque,online,bank_transfer,manual'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120', 'required_if:payment_method,cheque'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
