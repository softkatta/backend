<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeExitRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'status',
        'resignation_date',
        'last_working_day',
        'reason',
        'hr_remarks',
        'checklist',
    ];

    protected function casts(): array
    {
        return [
            'resignation_date' => 'date',
            'last_working_day' => 'date',
            'checklist' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
