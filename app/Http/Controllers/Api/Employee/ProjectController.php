<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EmployeeProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $query = $employee->projects()->latest();

        if ($request->filled('status') && $request->string('status') !== 'all') {
            $query->where('status', $request->string('status'));
        }

        return $this->success($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);
        $data = $this->validatedPayload($request);

        $project = $employee->projects()->create($data);

        return $this->success($project, 'Project created.', 201);
    }

    public function show(Request $request, EmployeeProject $project): JsonResponse
    {
        $this->assertOwnsProject($request, $project);

        return $this->success($project);
    }

    public function update(Request $request, EmployeeProject $project): JsonResponse
    {
        $this->assertOwnsProject($request, $project);
        $data = $this->validatedPayload($request, updating: true);
        $project->update($data);

        return $this->success($project->fresh(), 'Project updated.');
    }

    public function destroy(Request $request, EmployeeProject $project): JsonResponse
    {
        $this->assertOwnsProject($request, $project);
        $project->delete();

        return $this->success(null, 'Project deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(EmployeeProject::STATUSES)],
            'role' => ['nullable', 'string', 'max:120'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
    }

    private function assertOwnsProject(Request $request, EmployeeProject $project): void
    {
        $employee = $this->employeeFor($request);
        abort_unless((int) $project->employee_id === (int) $employee->id, 404);
    }
}
