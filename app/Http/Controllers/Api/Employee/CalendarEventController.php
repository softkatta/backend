<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EmployeeCalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CalendarEventController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $query = $employee->calendarEvents()->orderBy('starts_at');

        if ($request->filled('from') && $request->filled('to')) {
            $query->where('starts_at', '<=', $request->date('to')->endOfDay())
                ->where(function ($q) use ($request) {
                    $q->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $request->date('from')->startOfDay());
                });
        } elseif ($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', $request->string('month')->toString())) {
            $month = $request->string('month')->toString();
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->where('starts_at', '<=', $end)
                ->where(function ($q) use ($start) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $start);
                });
        }

        if ($request->filled('event_type') && $request->string('event_type') !== 'all') {
            $query->where('event_type', $request->string('event_type'));
        }

        return $this->success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);
        $data = $this->validatedPayload($request);

        $event = $employee->calendarEvents()->create($data);

        return $this->success($event, 'Calendar event created.', 201);
    }

    public function show(Request $request, EmployeeCalendarEvent $calendarEvent): JsonResponse
    {
        $this->assertOwns($request, $calendarEvent);

        return $this->success($calendarEvent);
    }

    public function update(Request $request, EmployeeCalendarEvent $calendarEvent): JsonResponse
    {
        $this->assertOwns($request, $calendarEvent);
        $data = $this->validatedPayload($request, updating: true);
        $calendarEvent->update($data);

        return $this->success($calendarEvent->fresh(), 'Calendar event updated.');
    }

    public function destroy(Request $request, EmployeeCalendarEvent $calendarEvent): JsonResponse
    {
        $this->assertOwns($request, $calendarEvent);
        $calendarEvent->delete();

        return $this->success(null, 'Calendar event deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_type' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(EmployeeCalendarEvent::TYPES)],
            'all_day' => ['boolean'],
            'starts_at' => [$required, 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function assertOwns(Request $request, EmployeeCalendarEvent $event): void
    {
        $employee = $this->employeeFor($request);
        abort_unless((int) $event->employee_id === (int) $employee->id, 404);
    }
}
