<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AttendanceRecordStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\AttendanceRecord;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceRecordController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceRecord::with('employee')->orderByDesc('work_date');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('month')) {
            [$year, $month] = explode('-', $request->string('month'));
            $query->whereYear('work_date', (int) $year)->whereMonth('work_date', (int) $month);
        }

        return $this->success($query->paginate($request->integer('per_page', 31)));
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord, EmployeePortalService $portal): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(AttendanceRecordStatus::values())],
            'hr_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $portal->updateAttendanceStatus($attendanceRecord, $data);

        return $this->success($updated, 'Attendance record updated.');
    }
}
