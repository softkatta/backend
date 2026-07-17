<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAsset extends Model
{
    public const CATEGORIES = [
        'laptop',
        'desktop',
        'monitor',
        'phone',
        'tablet',
        'id_card',
        'access_card',
        'peripheral',
        'other',
    ];

    public const STATUSES = [
        'available',
        'assigned',
        'maintenance',
        'retired',
    ];

    public const CONDITIONS = [
        'new',
        'good',
        'fair',
        'poor',
    ];

    protected $fillable = [
        'asset_tag',
        'name',
        'category',
        'brand',
        'model',
        'serial_number',
        'status',
        'condition',
        'notes',
        'purchased_at',
        'assigned_to',
        'assigned_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'assigned_at' => 'datetime',
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
