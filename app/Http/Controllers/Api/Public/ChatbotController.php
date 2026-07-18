<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Public\ChatbotLeadRequest;
use App\Http\Requests\Public\ChatbotMessageRequest;
use App\Http\Requests\Public\ChatbotSearchRequest;
use App\Http\Resources\ChatbotFaqResource;
use App\Services\ChatbotConversationService;
use App\Services\ChatbotFaqSearchService;
use App\Services\ChatbotLeadService;
use App\Services\ChatbotMessageService;
use App\Services\ChatbotSettingsService;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends BaseApiController
{
    public function __construct(
        private readonly ChatbotSettingsService $settings,
        private readonly ChatbotMessageService $messages,
        private readonly ChatbotFaqSearchService $faqSearch,
        private readonly ChatbotLeadService $leads,
        private readonly ChatbotConversationService $conversations,
        private readonly RecaptchaService $recaptcha,
    ) {}

    public function settings(): JsonResponse
    {
        return $this->success($this->settings->publicConfig());
    }

    public function quickReplies(): JsonResponse
    {
        return $this->success([
            'options' => $this->settings->quickReplyOptions(),
            'languages' => [
                ['key' => 'en', 'label' => 'English'],
                ['key' => 'mr', 'label' => 'मराठी'],
                ['key' => 'hi', 'label' => 'हिंदी'],
            ],
        ]);
    }

    public function sendMessage(ChatbotMessageRequest $request): JsonResponse
    {
        if (! $this->settings->publicConfig()['enabled']) {
            return $this->error('Chatbot is currently disabled.', 403);
        }

        return $this->success($this->messages->handle($request->validated(), $request));
    }

    public function searchFaq(ChatbotSearchRequest $request): JsonResponse
    {
        $data = $request->validated();
        $matches = $this->faqSearch->search(
            (string) $data['query'],
            (string) ($data['language'] ?? 'en'),
            $data['category'] ?? null,
            (int) ($data['limit'] ?? 5),
            $data['user_role'] ?? null,
        );

        return $this->success([
            'matches' => ChatbotFaqResource::collection(collect($matches)),
        ]);
    }

    public function saveConversation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'visitor_name' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string'],
            'response' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:5'],
        ]);

        $conversation = $this->conversations->record(
            $data['session_id'],
            $data['message'],
            $data['response'] ?? null,
            $data['language'] ?? 'en',
            $data['visitor_name'] ?? null,
            $request,
        );

        return $this->success($conversation, 'Conversation saved.', 201);
    }

    public function saveLead(ChatbotLeadRequest $request): JsonResponse
    {
        $this->recaptcha->verify($request->input('recaptcha_token'), $request->ip(), 'chatbot_lead');

        $lead = $this->leads->create($request->safe()->except(['recaptcha_token']));

        return $this->success($lead, 'Lead submitted successfully.', 201);
    }
}
