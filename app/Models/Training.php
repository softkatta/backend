<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Training extends Model
{
    public const CATEGORIES = [
        'onboarding',
        'technical',
        'compliance',
        'soft_skills',
        'leadership',
        'product',
        'other',
    ];

    public const MODES = [
        'online',
        'classroom',
        'self_paced',
        'workshop',
    ];

    public const STATUSES = [
        'assigned',
        'in_progress',
        'completed',
        'cancelled',
    ];

    protected $fillable = [
        'title',
        'description',
        'category',
        'provider',
        'mode',
        'duration_hours',
        'starts_at',
        'due_at',
        'status',
        'completion_percent',
        'completed_at',
        'certificate_url',
        'notes',
        'assigned_to',
        'assigned_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'assigned_at' => 'datetime',
            'duration_hours' => 'integer',
            'completion_percent' => 'integer',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
