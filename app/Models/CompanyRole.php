<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyRole extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'sort_order',
        'is_active',
        'employee_portal_menus',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'employee_portal_menus' => 'array',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function roleMenus(): HasMany
    {
        return $this->hasMany(CompanyRoleMenu::class);
    }

    public function portalMenus(): BelongsToMany
    {
        return $this->belongsToMany(PortalMenu::class, 'company_role_menus')
            ->withPivot(['is_enabled', 'sort_order'])
            ->withTimestamps();
    }
}
