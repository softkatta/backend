<?php

namespace App\Http\Requests\Public;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('would_recommend')) {
            $this->merge([
                'would_recommend' => filter_var($this->input('would_recommend'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        if ($this->has('consent')) {
            $this->merge([
                'consent' => filter_var($this->input('consent'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'review_type' => ['required', 'string', Rule::in([Review::TYPE_PRODUCT, Review::TYPE_SERVICE])],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'full_name' => ['required', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'would_recommend' => ['required', 'boolean'],
            'consent' => ['accepted'],
            'recaptcha_token' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('review_type');

            if ($type === Review::TYPE_PRODUCT && ! $this->filled('product_id')) {
                $validator->errors()->add('product_id', 'Please select a product.');
            }

            if ($type === Review::TYPE_SERVICE && ! $this->filled('service_id')) {
                $validator->errors()->add('service_id', 'Please select a service.');
            }

            if ($type === Review::TYPE_PRODUCT && $this->filled('service_id')) {
                $validator->errors()->add('service_id', 'Service is not allowed for product reviews.');
            }

            if ($type === Review::TYPE_SERVICE && $this->filled('product_id')) {
                $validator->errors()->add('product_id', 'Product is not allowed for service reviews.');
            }
        });
    }
}
