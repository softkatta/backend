<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\CompanyRoleMenuService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeePortalMenuMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin() || $user->role !== UserRole::Employee) {
            return $next($request);
        }

        $menuKey = CompanyRoleMenuService::menuKeyFromRequestPath($request->path());

        if ($menuKey === null) {
            return $next($request);
        }

        $companyRole = $user->employeeProfile?->companyRole;
        $slug = $companyRole?->slug;
        $category = $companyRole?->category;

        if (! CompanyRoleMenuService::canAccessMenuKey(
            $slug,
            $category,
            $menuKey,
            $companyRole?->employee_portal_menus,
            $companyRole,
        )) {
            return response()->json([
                'message' => 'This section is not available for your company role.',
            ], 403);
        }

        return $next($request);
    }
}
