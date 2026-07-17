<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PerformanceReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceReviewController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $reviews = PerformanceReview::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['shared', 'acknowledged'])
            ->latest('period_end')
            ->latest('id')
            ->paginate(20);

        return $this->success($reviews);
    }

    public function show(Request $request, PerformanceReview $performance_review): JsonResponse
    {
        $this->assertVisible($request, $performance_review);

        return $this->success($performance_review);
    }

    public function acknowledge(Request $request, PerformanceReview $performance_review): JsonResponse
    {
        $this->assertVisible($request, $performance_review);
        abort_unless($performance_review->status === 'shared', 422, 'Only shared reviews can be acknowledged.');

        $data = $request->validate([
            'employee_comments' => ['nullable', 'string', 'max:10000'],
        ]);

        $performance_review->update([
            'employee_comments' => $data['employee_comments'] ?? $performance_review->employee_comments,
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);

        return $this->success($performance_review->fresh(), 'Review acknowledged.');
    }

    private function assertVisible(Request $request, PerformanceReview $review): void
    {
        $employee = $this->employeeFor($request);
        abort_unless(
            (int) $review->employee_id === (int) $employee->id
            && in_array($review->status, ['shared', 'acknowledged'], true),
            404,
        );
    }
}
