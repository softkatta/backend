<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'overview' => ['nullable', 'string'],
            'logo' => ['nullable', 'string', 'max:500'],
            'banner' => ['nullable', 'string', 'max:500'],
            'login_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['boolean'],
            'has_free_trial' => ['boolean'],
            'trial_days' => ['integer', 'min:0'],
            'sort_order' => ['integer', 'min:0'],
            'meta' => ['nullable', 'array'],
            'meta.price_per_extra_user' => ['nullable', 'numeric', 'min:0'],
            'meta.price_per_extra_student' => ['nullable', 'numeric', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*.title' => ['required', 'string', 'max:255'],
            'features.*.description' => ['nullable', 'string'],
            'features.*.icon' => ['nullable', 'string', 'max:255'],
            'features.*.sort_order' => ['integer', 'min:0'],
            'screenshot' => ['nullable', 'string', 'max:500'],
            'demo_video_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
