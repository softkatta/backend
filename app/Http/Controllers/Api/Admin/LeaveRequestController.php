<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AttendanceRecordStatus;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveRequestController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::with(['employee', 'document'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->whereHas('employee', function ($q) use ($term): void {
                $q->where('full_name', 'like', $term)
                    ->orWhere('employee_code', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return $this->success($query->paginate($request->integer('per_page', 20)));
    }

    public function update(Request $request, LeaveRequest $leaveRequest, EmployeePortalService $portal): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(LeaveRequestStatus::values())],
            'hr_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $portal->updateLeaveStatus($leaveRequest, $data);

        return $this->success($updated, 'Leave request updated.');
    }
}
