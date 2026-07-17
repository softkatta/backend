<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChatbotFaq|array<string, mixed> */
class ChatbotFaqResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return [
                'id' => $this->resource['id'] ?? null,
                'question' => $this->resource['question'] ?? '',
                'answer' => $this->resource['answer'] ?? '',
                'category' => $this->resource['category'] ?? null,
                'language' => $this->resource['language'] ?? 'en',
                'keywords' => $this->resource['keywords'] ?? null,
                'sort_order' => $this->resource['sort_order'] ?? 0,
                'is_active' => (bool) ($this->resource['is_active'] ?? true),
            ];
        }

        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'category' => $this->category,
            'language' => $this->language,
            'keywords' => $this->keywords,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
