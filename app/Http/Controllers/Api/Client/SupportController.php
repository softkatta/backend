<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Client\StoreSupportTicketReplyRequest;
use App\Http\Requests\Client\StoreSupportTicketRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::with('assignee:id,name')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return $this->success($tickets);
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $user = $request->user();

        $ticket = SupportTicket::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'ticket_number' => 'SK-TKT-'.strtoupper(Str::random(8)),
            'subject' => $request->subject,
            'description' => $request->description,
            'priority' => $request->priority ?? TicketPriority::Medium,
            'status' => TicketStatus::Open,
        ]);

        return $this->success($ticket, 'Support ticket created.', 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success(
            $ticket->load([
                'replies' => fn ($q) => $q->where('is_internal', false)->with('user:id,name,avatar'),
            ])
        );
    }

    public function reply(StoreSupportTicketReplyRequest $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'is_internal' => false,
        ]);

        if (in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            $ticket->update(['status' => TicketStatus::Open]);
        }

        return $this->success($reply->load('user:id,name,avatar'), 'Reply sent.', 201);
    }
}
