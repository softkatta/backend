<?php

namespace App\Http\Requests\Admin;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateReviewRequest extends FormRequest
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
            'full_name' => ['sometimes', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'email' => ['sometimes', 'email', 'max:255'],
            'mobile' => ['sometimes', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'title' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'string', 'min:20', 'max:5000'],
            'would_recommend' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in([
                Review::STATUS_PENDING,
                Review::STATUS_APPROVED,
                Review::STATUS_REJECTED,
            ])],
            'is_featured' => ['sometimes', 'boolean'],
            'is_verified' => ['sometimes', 'boolean'],
            'admin_reply' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
