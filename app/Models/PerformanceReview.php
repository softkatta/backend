<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends Model
{
    public const RATINGS = [
        'exceeds',
        'meets',
        'partially_meets',
        'needs_improvement',
    ];

    public const STATUSES = [
        'draft',
        'shared',
        'acknowledged',
    ];

    protected $fillable = [
        'employee_id',
        'cycle_label',
        'period_start',
        'period_end',
        'reviewer_name',
        'overall_rating',
        'score',
        'strengths',
        'improvements',
        'goals',
        'manager_comments',
        'employee_comments',
        'status',
        'shared_at',
        'acknowledged_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'shared_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'score' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
