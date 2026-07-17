<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HelpdeskTicket extends Model
{
    public const CATEGORIES = [
        'it',
        'hr',
        'facilities',
        'payroll',
        'access',
        'other',
    ];

    public const PRIORITIES = [
        'low',
        'medium',
        'high',
        'urgent',
    ];

    public const STATUSES = [
        'open',
        'in_progress',
        'waiting',
        'resolved',
        'closed',
    ];

    protected $fillable = [
        'ticket_no',
        'employee_id',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'assigned_to_name',
        'resolution_notes',
        'resolved_at',
        'closed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HelpdeskTicket $ticket) {
            if (empty($ticket->ticket_no)) {
                $ticket->ticket_no = 'HD-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
            }
        });
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
