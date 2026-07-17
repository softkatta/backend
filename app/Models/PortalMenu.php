<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PortalMenu extends Model
{
    protected $fillable = [
        'portal',
        'key',
        'label',
        'route',
        'icon',
        'parent_key',
        'sort_order',
        'permission',
        'is_active',
        'badge_enabled',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'badge_enabled' => 'boolean',
        ];
    }

    public function companyRoles(): BelongsToMany
    {
        return $this->belongsToMany(CompanyRole::class, 'company_role_menus')
            ->withPivot(['is_enabled', 'sort_order'])
            ->withTimestamps();
    }
}
