<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\CompanyRole;
use Illuminate\Database\Eloquent\Builder;

final class AdminStaffDirectory
{
    /** @var list<string> */
    public const COMPANY_ROLE_SLUGS = [
        'receptionist',
        'software-developer',
        'ui-ux-designer',
    ];

    /**
     * @param  Builder<\App\Models\User>  $query
     */
    public static function applyScope(Builder $query): void
    {
        $roleIds = CompanyRole::query()
            ->whereIn('slug', self::COMPANY_ROLE_SLUGS)
            ->pluck('id');

        $query->where(function (Builder $inner) use ($roleIds): void {
            $inner->whereIn('role', [
                UserRole::SuperAdmin->value,
                UserRole::HrManager->value,
            ])->orWhere(function (Builder $employeeQuery) use ($roleIds): void {
                $employeeQuery
                    ->where('role', UserRole::Employee->value)
                    ->whereHas('employeeProfile', fn (Builder $profile) => $profile->whereIn('company_role_id', $roleIds));
            });
        });
    }

    /**
     * @param  Builder<\App\Models\User>  $query
     */
    public static function applyRoleFilter(Builder $query, string $staffRole): void
    {
        if ($staffRole === 'all') {
            return;
        }

        if ($staffRole === UserRole::SuperAdmin->value) {
            $query->where('role', UserRole::SuperAdmin->value);

            return;
        }

        if ($staffRole === UserRole::HrManager->value) {
            $query->where('role', UserRole::HrManager->value);

            return;
        }

        if (! in_array($staffRole, self::COMPANY_ROLE_SLUGS, true)) {
            return;
        }

        $roleId = CompanyRole::query()->where('slug', $staffRole)->value('id');
        if ($roleId === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query
            ->where('role', UserRole::Employee->value)
            ->whereHas('employeeProfile', fn (Builder $profile) => $profile->where('company_role_id', $roleId));
    }
}
