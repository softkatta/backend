<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $query = Training::query()
            ->where('assigned_to', $employee->id)
            ->latest('due_at')
            ->latest('id');

        if ($request->filled('status') && $request->string('status') !== 'all') {
            $query->where('status', $request->string('status'));
        }

        return $this->success($query->paginate(20));
    }

    public function show(Request $request, Training $training): JsonResponse
    {
        $this->assertOwns($request, $training);

        return $this->success($training);
    }

    public function update(Request $request, Training $training): JsonResponse
    {
        $this->assertOwns($request, $training);

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['assigned', 'in_progress', 'completed'])],
            'completion_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        $status = $data['status'] ?? $training->status;
        $percent = array_key_exists('completion_percent', $data)
            ? (int) $data['completion_percent']
            : (int) $training->completion_percent;

        if ($status === 'completed' || $percent >= 100) {
            $data['status'] = 'completed';
            $data['completion_percent'] = 100;
            $data['completed_at'] = $training->completed_at ?? now();
        } elseif ($percent > 0) {
            $data['status'] = 'in_progress';
            $data['completion_percent'] = $percent;
            $data['completed_at'] = null;
        } else {
            $data['status'] = $status === 'in_progress' ? 'in_progress' : 'assigned';
            $data['completion_percent'] = $percent;
            $data['completed_at'] = null;
        }

        $training->update($data);

        return $this->success($training->fresh(), 'Training progress updated.');
    }

    private function assertOwns(Request $request, Training $training): void
    {
        $employee = $this->employeeFor($request);
        abort_unless((int) $training->assigned_to === (int) $employee->id, 404);
    }
}
