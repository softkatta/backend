<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\HelpdeskTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HelpdeskTicketController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $query = HelpdeskTicket::query()
            ->where('employee_id', $employee->id)
            ->latest('id');

        if ($request->filled('status') && $request->string('status') !== 'all') {
            $query->where('status', $request->string('status'));
        }

        return $this->success($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'category' => ['nullable', 'string', Rule::in(HelpdeskTicket::CATEGORIES)],
            'priority' => ['nullable', 'string', Rule::in(HelpdeskTicket::PRIORITIES)],
        ]);

        $ticket = HelpdeskTicket::create([
            ...$data,
            'employee_id' => $employee->id,
            'status' => 'open',
            'created_by' => $request->user()?->id,
        ]);

        return $this->success($ticket, 'Ticket submitted.', 201);
    }

    public function show(Request $request, HelpdeskTicket $helpdesk_ticket): JsonResponse
    {
        $this->assertOwns($request, $helpdesk_ticket);

        return $this->success($helpdesk_ticket);
    }

    public function update(Request $request, HelpdeskTicket $helpdesk_ticket): JsonResponse
    {
        $this->assertOwns($request, $helpdesk_ticket);
        abort_unless(in_array($helpdesk_ticket->status, ['open', 'waiting'], true), 422, 'Only open or waiting tickets can be edited.');

        $data = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:20000'],
            'category' => ['sometimes', 'string', Rule::in(HelpdeskTicket::CATEGORIES)],
            'priority' => ['sometimes', 'string', Rule::in(HelpdeskTicket::PRIORITIES)],
            'status' => ['sometimes', 'string', Rule::in(['open', 'closed'])],
        ]);

        if (($data['status'] ?? null) === 'closed') {
            $data['closed_at'] = now();
            $data['resolved_at'] = $helpdesk_ticket->resolved_at ?? now();
        }

        $helpdesk_ticket->update($data);

        return $this->success($helpdesk_ticket->fresh(), 'Ticket updated.');
    }

    private function assertOwns(Request $request, HelpdeskTicket $ticket): void
    {
        $employee = $this->employeeFor($request);
        abort_unless((int) $ticket->employee_id === (int) $employee->id, 404);
    }
}
