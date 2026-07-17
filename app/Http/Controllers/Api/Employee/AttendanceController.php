<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $records = $employee->attendanceRecords()
            ->when($request->filled('month'), function ($query) use ($request): void {
                [$year, $month] = explode('-', $request->string('month'));
                $query->whereYear('work_date', (int) $year)->whereMonth('work_date', (int) $month);
            })
            ->orderByDesc('work_date')
            ->paginate(31);

        return $this->success($records);
    }

    public function store(Request $request, EmployeePortalService $portal): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'work_mode' => ['nullable', 'string', Rule::in(['office', 'wfh', 'field'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $record = $portal->submitAttendance($employee, $data);

        return $this->success($record, 'Attendance submitted.', 201);
    }
}
