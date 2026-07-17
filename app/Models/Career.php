<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Career extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'department',
        'company_role_id',
        'location',
        'employment_type',
        'experience_required',
        'salary_display',
        'excerpt',
        'description',
        'requirements',
        'apply_email',
        'apply_url',
        'is_published',
        'published_at',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function companyRole(): BelongsTo
    {
        return $this->belongsTo(CompanyRole::class);
    }
}
