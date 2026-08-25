<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
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
            'meta.releases' => ['nullable', 'array'],
            'meta.releases.android' => ['nullable', 'array'],
            'meta.releases.android.version' => ['nullable', 'string', 'max:50'],
            'meta.releases.android.file_path' => ['nullable', 'string', 'max:500'],
            'meta.releases.android.file_name' => ['nullable', 'string', 'max:255'],
            'meta.releases.windows' => ['nullable', 'array'],
            'meta.releases.windows.version' => ['nullable', 'string', 'max:50'],
            'meta.releases.windows.file_path' => ['nullable', 'string', 'max:500'],
            'meta.releases.windows.file_name' => ['nullable', 'string', 'max:255'],
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
