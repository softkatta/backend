<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class ChatbotSearchRequest extends FormRequest
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
            'query' => ['required', 'string', 'max:500'],
            'language' => ['nullable', 'string', 'max:5'],
            'category' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
            'user_role' => ['nullable', 'string', 'in:admin,employee,client,hr'],
        ];
    }
}
