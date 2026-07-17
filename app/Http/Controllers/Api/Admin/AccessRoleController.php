<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\PermissionCatalogService;
use App\Services\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccessRoleController extends BaseApiController
{
    public function index(RolePermissionService $service): JsonResponse
    {
        return $this->success($service->snapshot());
    }

    public function sync(RolePermissionService $service): JsonResponse
    {
        $service->syncCatalog();

        return $this->success($service->snapshot(), 'Roles and permissions synced from catalog.');
    }

    public function update(Request $request, string $role, RolePermissionService $service): JsonResponse
    {
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalogService::permissionNames())],
        ]);

        $updated = $service->updateRolePermissions($role, $data['permissions']);

        return $this->success([
            'name' => $updated->name,
            'permissions' => $updated->permissions->pluck('name')->values(),
        ], 'Role permissions updated.');
    }
}
