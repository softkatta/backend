<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Employee;
use App\Models\PerformanceReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerformanceReviewController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = PerformanceReview::query()
            ->with(['employee:id,full_name,employee_code,email', 'creator:id,name'])
            ->latest('id');

        if ($request->filled('status') && $request->string('status') !== 'all') {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('cycle_label', 'like', $term)
                    ->orWhere('reviewer_name', 'like', $term)
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

        $review = PerformanceReview::create($data);

        return $this->success(
            $review->load(['employee:id,full_name,employee_code,email', 'creator:id,name']),
            'Performance review created.',
            201,
        );
    }

    public function show(PerformanceReview $performance_review): JsonResponse
    {
        return $this->success(
            $performance_review->load(['employee:id,full_name,employee_code,email', 'creator:id,name']),
        );
    }

    public function update(Request $request, PerformanceReview $performance_review): JsonResponse
    {
        $data = $this->validated($request, updating: true);
        $data = $this->applyStatusTimestamps($data, $performance_review);

        $performance_review->update($data);

        return $this->success(
            $performance_review->fresh()->load(['employee:id,full_name,employee_code,email', 'creator:id,name']),
            'Performance review updated.',
        );
    }

    public function destroy(PerformanceReview $performance_review): JsonResponse
    {
        $this->permanentlyDelete($performance_review);

        return $this->success(null, 'Performance review deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'employee_id' => [$required, 'integer', 'exists:employees,id'],
            'cycle_label' => [$required, 'string', 'max:120'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'reviewer_name' => ['nullable', 'string', 'max:255'],
            'overall_rating' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(PerformanceReview::RATINGS)],
            'score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'strengths' => ['nullable', 'string', 'max:10000'],
            'improvements' => ['nullable', 'string', 'max:10000'],
            'goals' => ['nullable', 'string', 'max:10000'],
            'manager_comments' => ['nullable', 'string', 'max:10000'],
            'employee_comments' => ['nullable', 'string', 'max:10000'],
            'status' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(PerformanceReview::STATUSES)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyStatusTimestamps(array $data, ?PerformanceReview $existing = null): array
    {
        if (array_key_exists('employee_id', $data)) {
            abort_unless(Employee::query()->whereKey($data['employee_id'])->exists(), 422, 'Employee not found.');
        }

        $status = $data['status'] ?? $existing?->status ?? 'draft';

        if ($status === 'shared' && (! $existing || $existing->status === 'draft')) {
            $data['shared_at'] = $existing?->shared_at ?? now();
        }

        if ($status === 'acknowledged') {
            $data['shared_at'] = $existing?->shared_at ?? now();
            $data['acknowledged_at'] = $existing?->acknowledged_at ?? now();
        }

        if ($status === 'draft') {
            $data['shared_at'] = null;
            $data['acknowledged_at'] = null;
        }

        return $data;
    }
}
