<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyRoleMenu extends Model
{
    protected $fillable = [
        'company_role_id',
        'portal_menu_id',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function companyRole(): BelongsTo
    {
        return $this->belongsTo(CompanyRole::class);
    }

    public function portalMenu(): BelongsTo
    {
        return $this->belongsTo(PortalMenu::class);
    }
}
