<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\HelpdeskTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HelpdeskTicketController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = HelpdeskTicket::query()
            ->with(['employee:id,full_name,employee_code,email', 'creator:id,name'])
            ->latest('id');

        if ($request->filled('status') && $request->string('status') !== 'all') {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('priority') && $request->string('priority') !== 'all') {
            $query->where('priority', $request->string('priority'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('ticket_no', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhereHas('employee', fn ($eq) => $eq->where('full_name', 'like', $term));
            });
        }

        return $this->success($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;
        $data = $this->applyStatusTimestamps($data);

        $ticket = HelpdeskTicket::create($data);

        return $this->success(
            $ticket->load(['employee:id,full_name,employee_code,email', 'creator:id,name']),
            'Ticket created.',
            201,
        );
    }

    public function show(HelpdeskTicket $helpdesk_ticket): JsonResponse
    {
        return $this->success(
            $helpdesk_ticket->load(['employee:id,full_name,employee_code,email', 'creator:id,name']),
        );
    }

    public function update(Request $request, HelpdeskTicket $helpdesk_ticket): JsonResponse
    {
        $data = $this->validated($request, updating: true);
        $data = $this->applyStatusTimestamps($data, $helpdesk_ticket);

        $helpdesk_ticket->update($data);

        return $this->success(
            $helpdesk_ticket->fresh()->load(['employee:id,full_name,employee_code,email', 'creator:id,name']),
            'Ticket updated.',
        );
    }

    public function destroy(HelpdeskTicket $helpdesk_ticket): JsonResponse
    {
        $this->permanentlyDelete($helpdesk_ticket);

        return $this->success(null, 'Ticket deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'employee_id' => [$required, 'integer', 'exists:employees,id'],
            'subject' => [$required, 'string', 'max:255'],
            'description' => [$required, 'string', 'max:20000'],
            'category' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(HelpdeskTicket::CATEGORIES)],
            'priority' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(HelpdeskTicket::PRIORITIES)],
            'status' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(HelpdeskTicket::STATUSES)],
            'assigned_to_name' => ['nullable', 'string', 'max:255'],
            'resolution_notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyStatusTimestamps(array $data, ?HelpdeskTicket $existing = null): array
    {
        $status = $data['status'] ?? $existing?->status ?? 'open';

        if ($status === 'resolved') {
            $data['resolved_at'] = $existing?->resolved_at ?? now();
            $data['closed_at'] = null;
        } elseif ($status === 'closed') {
            $data['resolved_at'] = $existing?->resolved_at ?? now();
            $data['closed_at'] = $existing?->closed_at ?? now();
        } elseif (in_array($status, ['open', 'in_progress', 'waiting'], true)) {
            $data['resolved_at'] = null;
            $data['closed_at'] = null;
        }

        return $data;
    }
}
