<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCalendarEvent extends Model
{
    public const TYPES = ['event', 'meeting', 'reminder', 'deadline', 'personal'];

    protected $fillable = [
        'employee_id',
        'title',
        'description',
        'event_type',
        'all_day',
        'starts_at',
        'ends_at',
        'location',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'all_day' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
