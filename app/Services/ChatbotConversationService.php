<?php

namespace App\Services;

use App\Models\ChatbotConversation;
use Illuminate\Http\Request;

class ChatbotConversationService
{
    public function record(
        string $sessionId,
        string $message,
        ?string $response,
        string $language = 'en',
        ?string $visitorName = null,
        ?Request $request = null,
    ): ChatbotConversation {
        return ChatbotConversation::create([
            'session_id' => $sessionId,
            'visitor_name' => $visitorName,
            'message' => $message,
            'response' => $response,
            'language' => $language,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
