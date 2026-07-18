<?php

namespace App\Http\Requests\Public;

use App\Enums\ApplicationDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if ($phone === null || $phone === '') {
            $this->merge(['phone' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fileRule = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\d{10}$/'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'string', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'current_address' => ['required', 'string', 'max:2000'],
            'permanent_address' => ['required', 'string', 'max:2000'],
            'qualification' => ['required', 'string', 'max:255'],
            'skills' => ['required', 'string', 'max:2000'],
            'total_experience' => ['required', 'string', 'max:100'],
            'current_company' => ['nullable', 'string', 'max:255'],
            'current_salary' => ['nullable', 'numeric', 'min:0'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'notice_period' => ['nullable', 'string', 'max:100'],
            'preferred_location' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            ...collect(ApplicationDocumentType::cases())
                ->mapWithKeys(fn (ApplicationDocumentType $type) => [
                    $type->value => $type->required()
                        ? ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']
                        : $fileRule,
                ])
                ->all(),
            'recaptcha_token' => ['nullable', 'string'],
        ];
    }
}
