<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ChatbotCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatbotCategoryController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            ChatbotCategory::query()->orderBy('sort_order')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category = ChatbotCategory::create($data);

        return $this->success($category, 'Category created.', 201);
    }

    public function update(Request $request, ChatbotCategory $chatbot_category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $chatbot_category->update($data);

        return $this->success($chatbot_category->fresh(), 'Category updated.');
    }

    public function destroy(ChatbotCategory $chatbot_category): JsonResponse
    {
        $this->permanentlyDelete($chatbot_category);

        return $this->success(null, 'Category deleted.');
    }
}
