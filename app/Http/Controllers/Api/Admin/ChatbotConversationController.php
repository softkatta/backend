<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ChatbotConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotConversationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = ChatbotConversation::query()->latest('created_at');

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->string('session_id'));
        }

        return $this->success($query->paginate(25));
    }
}
