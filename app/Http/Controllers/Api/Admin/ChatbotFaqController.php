<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ChatbotFaqResource;
use App\Models\ChatbotFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotFaqController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = ChatbotFaq::query()->orderBy('sort_order');

        if ($request->filled('language')) {
            $query->where('language', $request->string('language'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return $this->success(ChatbotFaqResource::collection($query->get()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'keywords' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:5'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $faq = ChatbotFaq::create($data);

        return $this->success(new ChatbotFaqResource($faq), 'Chatbot FAQ created.', 201);
    }

    public function update(Request $request, ChatbotFaq $chatbot_faq): JsonResponse
    {
        $data = $request->validate([
            'question' => ['sometimes', 'string', 'max:500'],
            'answer' => ['sometimes', 'string'],
            'keywords' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:5'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $chatbot_faq->update($data);

        return $this->success(new ChatbotFaqResource($chatbot_faq->fresh()), 'Chatbot FAQ updated.');
    }

    public function destroy(ChatbotFaq $chatbot_faq): JsonResponse
    {
        $this->permanentlyDelete($chatbot_faq);

        return $this->success(null, 'Chatbot FAQ deleted.');
    }
}
