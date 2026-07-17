<?php

namespace App\Services;

use App\Enums\ChatbotLeadStatus;
use App\Models\ChatbotLead;

class ChatbotLeadService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ChatbotLead
    {
        return ChatbotLead::create([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'product' => $data['product'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => ChatbotLeadStatus::New,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ChatbotLead $lead, array $data): ChatbotLead
    {
        $lead->update($data);

        return $lead->fresh(['assignee']);
    }
}
