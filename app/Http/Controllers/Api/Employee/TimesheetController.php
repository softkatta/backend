<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EmployeeTimesheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimesheetController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $query = $employee->timesheets()->with('project:id,name')->latest('work_date')->latest('id');

        if ($request->filled('month')) {
            $month = $request->string('month')->toString();
            if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                $query->whereYear('work_date', (int) substr($month, 0, 4))
                    ->whereMonth('work_date', (int) substr($month, 5, 2));
            }
        }

        if ($request->filled('status') && $request->string('status') !== 'all') {
            $query->where('status', $request->string('status'));
        }

        return $this->success($query->paginate(31));
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);
        $data = $this->validatedPayload($request);
        $this->assertProjectOwnership($employee->id, $data['employee_project_id'] ?? null);

        if (! empty($data['employee_project_id']) && empty($data['project_label'])) {
            $project = $employee->projects()->whereKey($data['employee_project_id'])->first();
            $data['project_label'] = $project?->name;
        }

        $entry = $employee->timesheets()->create($data);

        return $this->success($entry->load('project:id,name'), 'Timesheet entry saved.', 201);
    }

    public function show(Request $request, EmployeeTimesheet $timesheet): JsonResponse
    {
        $this->assertOwns($request, $timesheet);

        return $this->success($timesheet->load('project:id,name'));
    }

    public function update(Request $request, EmployeeTimesheet $timesheet): JsonResponse
    {
        $this->assertOwns($request, $timesheet);
        abort_unless(in_array($timesheet->status, ['draft', 'submitted'], true), 422, 'Approved or rejected entries cannot be edited.');

        $employee = $this->employeeFor($request);
        $data = $this->validatedPayload($request, updating: true);
        $this->assertProjectOwnership($employee->id, $data['employee_project_id'] ?? null);

        if (array_key_exists('employee_project_id', $data) && ! empty($data['employee_project_id']) && empty($data['project_label'])) {
            $project = $employee->projects()->whereKey($data['employee_project_id'])->first();
            $data['project_label'] = $project?->name;
        }

        $timesheet->update($data);

        return $this->success($timesheet->fresh()->load('project:id,name'), 'Timesheet entry updated.');
    }

    public function destroy(Request $request, EmployeeTimesheet $timesheet): JsonResponse
    {
        $this->assertOwns($request, $timesheet);
        abort_unless(in_array($timesheet->status, ['draft', 'submitted'], true), 422, 'Approved or rejected entries cannot be deleted.');

        $timesheet->delete();

        return $this->success(null, 'Timesheet entry deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'work_date' => [$required, 'date'],
            'hours' => [$required, 'numeric', 'min:0.25', 'max:24'],
            'project_label' => ['nullable', 'string', 'max:255'],
            'employee_project_id' => ['nullable', 'integer', 'exists:employee_projects,id'],
            'status' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(['draft', 'submitted'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function assertOwns(Request $request, EmployeeTimesheet $timesheet): void
    {
        $employee = $this->employeeFor($request);
        abort_unless((int) $timesheet->employee_id === (int) $employee->id, 404);
    }

    private function assertProjectOwnership(int $employeeId, mixed $projectId): void
    {
        if ($projectId === null || $projectId === '') {
            return;
        }

        $owns = \App\Models\EmployeeProject::query()
            ->where('id', $projectId)
            ->where('employee_id', $employeeId)
            ->exists();

        abort_unless($owns, 422, 'Selected project does not belong to this employee.');
    }
}
