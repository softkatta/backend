<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Employee;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Training::query()
            ->with(['assignee:id,full_name,employee_code,email', 'creator:id,name'])
            ->latest('id');

        if ($request->filled('status') && $request->string('status') !== 'all') {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('provider', 'like', $term)
                    ->orWhere('category', 'like', $term);
            });
        }

        return $this->success($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;
        $data = $this->normalizeProgress($data);

        if (empty($data['assigned_at'])) {
            $data['assigned_at'] = now();
        }

        $training = Training::create($data);

        return $this->success(
            $training->load(['assignee:id,full_name,employee_code,email', 'creator:id,name']),
            'Training assigned.',
            201,
        );
    }

    public function show(Training $training): JsonResponse
    {
        return $this->success(
            $training->load(['assignee:id,full_name,employee_code,email', 'creator:id,name']),
        );
    }

    public function update(Request $request, Training $training): JsonResponse
    {
        $data = $this->validated($request, updating: true);
        $data = $this->normalizeProgress($data, $training);

        $training->update($data);

        return $this->success(
            $training->fresh()->load(['assignee:id,full_name,employee_code,email', 'creator:id,name']),
            'Training updated.',
        );
    }

    public function destroy(Training $training): JsonResponse
    {
        $this->permanentlyDelete($training);

        return $this->success(null, 'Training deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(Training::CATEGORIES)],
            'provider' => ['nullable', 'string', 'max:255'],
            'mode' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(Training::MODES)],
            'duration_hours' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'status' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(Training::STATUSES)],
            'completion_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'certificate_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => [$required, 'integer', 'exists:employees,id'],
            'assigned_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeProgress(array $data, ?Training $existing = null): array
    {
        if (array_key_exists('assigned_to', $data)) {
            abort_unless(Employee::query()->whereKey($data['assigned_to'])->exists(), 422, 'Employee not found.');
        }

        $status = $data['status'] ?? $existing?->status ?? 'assigned';
        $percent = array_key_exists('completion_percent', $data)
            ? (int) $data['completion_percent']
            : (int) ($existing?->completion_percent ?? 0);

        if ($status === 'completed') {
            $data['completion_percent'] = 100;
            if (! ($existing?->completed_at) || ($existing->status !== 'completed')) {
                $data['completed_at'] = now();
            }
        } elseif ($status === 'cancelled') {
            $data['completed_at'] = null;
        } else {
            if ($percent >= 100) {
                $data['status'] = 'completed';
                $data['completion_percent'] = 100;
                $data['completed_at'] = now();
            } elseif ($percent > 0 && $status === 'assigned') {
                $data['status'] = 'in_progress';
                $data['completed_at'] = null;
            } else {
                $data['completed_at'] = null;
            }
        }

        return $data;
    }
}
