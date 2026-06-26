<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TicketStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreSupportTicketReplyRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            SupportTicket::withoutGlobalScopes()
                ->with(['user', 'assignee', 'tenant'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(SupportTicket $supportTicket): JsonResponse
    {
        return $this->success(
            SupportTicket::withoutGlobalScopes()
                ->with(['user', 'assignee', 'tenant', 'replies.user'])
                ->findOrFail($supportTicket->id)
        );
    }

    public function update(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $ticket = SupportTicket::withoutGlobalScopes()->findOrFail($supportTicket->id);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:open,in_progress,waiting_on_client,resolved,closed'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $ticket->update($data);

        return $this->success($ticket->fresh()->load('assignee'), 'Ticket updated.');
    }

    public function reply(StoreSupportTicketReplyRequest $request, SupportTicket $supportTicket): JsonResponse
    {
        $ticket = SupportTicket::withoutGlobalScopes()->findOrFail($supportTicket->id);

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'is_internal' => $request->boolean('is_internal'),
        ]);

        if ($ticket->status === TicketStatus::Open) {
            $ticket->update(['status' => TicketStatus::InProgress]);
        }

        return $this->success($reply->load('user:id,name,email'), 'Reply sent.', 201);
    }
}
