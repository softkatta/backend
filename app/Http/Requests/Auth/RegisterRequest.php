<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'company' => ['nullable', 'string', 'max:255'],
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered. Please sign in instead.',
            'phone.regex' => 'Phone must be a 10-digit mobile number.',
            'avatar.required' => 'Please upload a profile photo.',
            'avatar.image' => 'Profile photo must be a valid image (JPG, PNG, or WEBP).',
            'avatar.mimes' => 'Profile photo must be JPG, PNG, or WEBP.',
            'password' => 'Password must be at least 8 characters.',
        ];
    }
}
