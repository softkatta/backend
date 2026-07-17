<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'job_application_id',
        'employee_code',
        'full_name',
        'email',
        'phone',
        'department',
        'company_role_id',
        'designation',
        'date_of_joining',
        'reporting_manager',
        'salary_details',
        'pf_uan',
        'esic_number',
        'bank_details',
        'emergency_contact',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_joining' => 'date',
            'salary_details' => 'array',
            'bank_details' => 'array',
            'emergency_contact' => 'array',
        ];
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function companyRole(): BelongsTo
    {
        return $this->belongsTo(CompanyRole::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EmployeeTask::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(EmployeeProject::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(EmployeeTimesheet::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(EmployeeCalendarEvent::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CompanyAsset::class, 'assigned_to');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class, 'assigned_to');
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    public function helpdeskTickets(): HasMany
    {
        return $this->hasMany(HelpdeskTicket::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function exitRecord(): HasOne
    {
        return $this->hasOne(EmployeeExitRecord::class);
    }
}
