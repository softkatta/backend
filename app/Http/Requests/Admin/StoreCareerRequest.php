<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareerRequest extends FormRequest
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
        $careerId = $this->route('career')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('careers', 'slug')->ignore($careerId)],
            'department' => ['nullable', 'string', 'max:255'],
            'company_role_id' => ['nullable', 'integer', 'exists:company_roles,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:50', Rule::in(['full-time', 'part-time', 'contract', 'internship', 'remote', 'hybrid'])],
            'experience_required' => ['nullable', 'string', 'max:255'],
            'salary_display' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string'],
            'requirements' => ['nullable', 'string'],
            'apply_email' => ['nullable', 'email', 'max:255'],
            'apply_url' => ['nullable', 'url', 'max:500'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
