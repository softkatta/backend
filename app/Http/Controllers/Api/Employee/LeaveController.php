<?php

namespace App\Http\Controllers\Api\Employee;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\LeaveRequest;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);
        $leaves = $employee->leaveRequests()->with('document')->latest()->paginate(20);

        return $this->success($leaves);
    }

    public function store(Request $request, EmployeePortalService $portal): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $data = $request->validate([
            'leave_type' => ['required', 'string', Rule::in(['casual', 'sick', 'earned', 'unpaid', 'other'])],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $leave = $portal->submitLeave($employee, $data, $request->file('attachment'));

        return $this->success($leave->load('document'), 'Leave application submitted.', 201);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $this->employeeFor($request);
        abort_unless((int) $leaveRequest->employee_id === (int) $employee->id, 404);
        abort_unless($leaveRequest->status === LeaveRequestStatus::Pending->value, 422, 'Only pending leave can be cancelled.');

        $leaveRequest->update(['status' => LeaveRequestStatus::Cancelled->value]);

        return $this->success($leaveRequest->fresh(), 'Leave request cancelled.');
    }
}
