<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ChatbotLeadStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\ChatbotLead;
use App\Services\ChatbotLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatbotLeadController extends BaseApiController
{
    public function __construct(private readonly ChatbotLeadService $leads) {}

    public function index(Request $request): JsonResponse
    {
        $query = ChatbotLead::query()->with('assignee:id,name,email')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->success($query->paginate(20));
    }

    public function update(Request $request, ChatbotLead $chatbot_lead): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'company_name' => ['nullable', 'string', 'max:190'],
            'product' => ['nullable', 'string', 'max:190'],
            'message' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(ChatbotLeadStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        return $this->success($this->leads->update($chatbot_lead, $data), 'Lead updated.');
    }

    public function destroy(ChatbotLead $chatbot_lead): JsonResponse
    {
        $this->permanentlyDelete($chatbot_lead);

        return $this->success(null, 'Lead deleted.');
    }
}
