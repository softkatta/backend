<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class ChatbotMessageRequest extends FormRequest
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
            'session_id' => ['nullable', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', 'string', 'in:welcome,message,quick_reply'],
            'quick_reply' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:5'],
            'visitor_name' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:100'],
            'user_role' => ['nullable', 'string', 'in:admin,employee,client,hr'],
        ];
    }
}
