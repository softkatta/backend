<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EmployeeTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $query = $employee->tasks()->latest();

        if ($request->filled('status') && $request->string('status') !== 'all') {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('priority') && $request->string('priority') !== 'all') {
            $query->where('priority', $request->string('priority'));
        }

        return $this->success($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);
        $data = $this->validatedPayload($request);

        $task = $employee->tasks()->create([
            ...$data,
            'completed_at' => ($data['status'] ?? 'todo') === 'done' ? now() : null,
        ]);

        return $this->success($task, 'Task created.', 201);
    }

    public function show(Request $request, EmployeeTask $task): JsonResponse
    {
        $this->assertOwnsTask($request, $task);

        return $this->success($task);
    }

    public function update(Request $request, EmployeeTask $task): JsonResponse
    {
        $this->assertOwnsTask($request, $task);
        $data = $this->validatedPayload($request, updating: true);

        if (array_key_exists('status', $data)) {
            if ($data['status'] === 'done' && $task->status !== 'done') {
                $data['completed_at'] = now();
            } elseif ($data['status'] !== 'done') {
                $data['completed_at'] = null;
            }
        }

        $task->update($data);

        return $this->success($task->fresh(), 'Task updated.');
    }

    public function destroy(Request $request, EmployeeTask $task): JsonResponse
    {
        $this->assertOwnsTask($request, $task);
        $task->delete();

        return $this->success(null, 'Task deleted.');
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
            'status' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(EmployeeTask::STATUSES)],
            'priority' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(EmployeeTask::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
        ]);
    }

    private function assertOwnsTask(Request $request, EmployeeTask $task): void
    {
        $employee = $this->employeeFor($request);
        abort_unless((int) $task->employee_id === (int) $employee->id, 404);
    }
}
