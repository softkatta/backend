<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProject extends Model
{
    public const STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

    protected $fillable = [
        'employee_id',
        'name',
        'description',
        'status',
        'role',
        'progress',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
