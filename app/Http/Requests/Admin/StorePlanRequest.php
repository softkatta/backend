<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly', 'enterprise'])],
            'features' => ['nullable', 'array'],
            'limits' => ['nullable', 'array'],
            'limits.max_users' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'limits.max_staff' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'limits.max_students' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'limits.max_branches' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'limits.max_storage' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'limits.enabled_modules' => ['nullable', 'array'],
            'is_popular' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $limits = $this->input('limits');
        if (! is_array($limits)) {
            return;
        }

        // Keep staff/users in sync so Company API + products both work.
        if (isset($limits['max_users']) && ! isset($limits['max_staff'])) {
            $limits['max_staff'] = $limits['max_users'];
        } elseif (isset($limits['max_staff']) && ! isset($limits['max_users'])) {
            $limits['max_users'] = $limits['max_staff'];
        }

        $this->merge(['limits' => $limits]);
    }
}
