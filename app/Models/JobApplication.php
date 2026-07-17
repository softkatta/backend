<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobApplication extends Model
{
    protected $fillable = [
        'career_id',
        'employee_id',
        'name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'current_address',
        'permanent_address',
        'qualification',
        'skills',
        'total_experience',
        'current_company',
        'current_salary',
        'expected_salary',
        'notice_period',
        'preferred_location',
        'message',
        'hr_remarks',
        'interview_scheduled_at',
        'resume_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'current_salary' => 'decimal:2',
            'expected_salary' => 'decimal:2',
            'interview_scheduled_at' => 'datetime',
        ];
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }
}
