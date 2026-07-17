<?php

namespace App\Services;

use App\Enums\AttendanceRecordStatus;
use App\Enums\EmployeeDocumentCategory;
use App\Enums\EmployeeExitStatus;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeExitRecord;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class EmployeePortalService
{
    public function __construct(
        private readonly EmployeeService $employees,
        private readonly HrStorageService $storage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(Employee $employee): array
    {
        $employee->load(['documents', 'exitRecord', 'leaveRequests' => fn ($q) => $q->latest()->limit(5)]);

        return [
            'employee' => $employee,
            'stats' => [
                'pending_leaves' => $employee->leaveRequests()->where('status', LeaveRequestStatus::Pending->value)->count(),
                'approved_leaves' => $employee->leaveRequests()->where('status', LeaveRequestStatus::Approved->value)->count(),
                'documents' => $employee->documents()->count(),
                'attendance_this_month' => $employee->attendanceRecords()
                    ->whereMonth('work_date', now()->month)
                    ->whereYear('work_date', now()->year)
                    ->count(),
            ],
            'recent_leaves' => $employee->leaveRequests()->latest()->limit(5)->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitLeave(Employee $employee, array $data, ?UploadedFile $attachment = null): LeaveRequest
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        abort_if($end->lt($start), 422, 'End date must be on or after start date.');

        $totalDays = (int) $start->diffInDays($end) + 1;

        $documentId = null;
        if ($attachment) {
            $document = $this->employees->uploadDocument(
                $employee,
                $attachment,
                EmployeeDocumentCategory::LeaveApplication->value,
                'Leave application attachment',
            );
            $documentId = $document->id;
        }

        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type' => $data['leave_type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_days' => $totalDays,
            'reason' => $data['reason'],
            'status' => LeaveRequestStatus::Pending->value,
            'document_id' => $documentId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitAttendance(Employee $employee, array $data): AttendanceRecord
    {
        return AttendanceRecord::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'work_date' => $data['work_date'],
            ],
            [
                'check_in' => $data['check_in'] ?? null,
                'check_out' => $data['check_out'] ?? null,
                'work_mode' => $data['work_mode'] ?? 'office',
                'notes' => $data['notes'] ?? null,
                'status' => AttendanceRecordStatus::Submitted->value,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitResignation(Employee $employee, array $data, ?UploadedFile $letter = null): EmployeeExitRecord
    {
        if ($letter) {
            $this->employees->uploadDocument(
                $employee,
                $letter,
                EmployeeDocumentCategory::ResignationForm->value,
                'Employee resignation letter',
            );
        }

        return $this->employees->initiateExit($employee, [
            'resignation_date' => $data['resignation_date'] ?? now()->toDateString(),
            'last_working_day' => $data['last_working_day'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function uploadSelfServiceDocument(Employee $employee, UploadedFile $file, string $category, ?string $notes = null)
    {
        abort_unless(
            in_array($category, EmployeeDocumentCategory::selfServiceValues(), true),
            422,
            'This document type must be submitted from the employee portal.',
        );

        return $this->employees->uploadDocument($employee, $file, $category, $notes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateLeaveStatus(LeaveRequest $leave, array $data): LeaveRequest
    {
        $leave->update([
            'status' => $data['status'],
            'hr_remarks' => $data['hr_remarks'] ?? $leave->hr_remarks,
        ]);

        return $leave->fresh(['employee', 'document']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAttendanceStatus(AttendanceRecord $record, array $data): AttendanceRecord
    {
        $record->update([
            'status' => $data['status'],
            'hr_remarks' => $data['hr_remarks'] ?? $record->hr_remarks,
        ]);

        return $record->fresh('employee');
    }
}
