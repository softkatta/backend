<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Tenant;
use App\Services\SecurityService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends BaseApiController
{
    public function index(Request $request, SecurityService $security): JsonResponse
    {
        $query = Tenant::query()->with('owner:id,name,email')->latest();
        $security->applyAdminWorkspaceScope($query, $request, 'id');

        return $this->success(
            $query->paginate(20)
        );
    }

    public function store(Request $request, TenantService $service, SecurityService $security): JsonResponse
    {
        if ($security->adminWorkspaceMode($request) === 'demo') {
            return $this->error('New tenants cannot be created in demo workspace mode.', 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:tenants,slug'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain'],
            'backend_domain' => ['nullable', 'string', 'max:255', 'unique:tenants,backend_domain'],
            'frontend_domain' => ['nullable', 'string', 'max:255', 'unique:tenants,frontend_domain'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:active,suspended,inactive'],
            'settings' => ['nullable', 'array'],
        ]);

        $tenant = $service->create($data);

        return $this->success($tenant, 'Tenant created.', 201);
    }

    public function show(Request $request, Tenant $tenant, SecurityService $security): JsonResponse
    {
        $query = Tenant::query()->with(['owner', 'users', 'subscriptions']);
        $security->applyAdminWorkspaceScope($query, $request, 'id');

        return $this->success($query->findOrFail($tenant->id));
    }

    public function update(Request $request, Tenant $tenant, TenantService $service, SecurityService $security): JsonResponse
    {
        $query = Tenant::query();
        $security->applyAdminWorkspaceScope($query, $request, 'id');
        $scopedTenant = $query->findOrFail($tenant->id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:tenants,slug,'.$tenant->id],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain,'.$tenant->id],
            'backend_domain' => ['nullable', 'string', 'max:255', 'unique:tenants,backend_domain,'.$tenant->id],
            'frontend_domain' => ['nullable', 'string', 'max:255', 'unique:tenants,frontend_domain,'.$tenant->id],
            'status' => ['nullable', 'string', 'in:active,suspended,inactive'],
            'settings' => ['nullable', 'array'],
        ]);

        $tenant = $service->update($scopedTenant, $data);

        return $this->success($tenant, 'Tenant updated.');
    }

    public function destroy(Request $request, Tenant $tenant, SecurityService $security): JsonResponse
    {
        $query = Tenant::query();
        $security->applyAdminWorkspaceScope($query, $request, 'id');
        $scopedTenant = $query->findOrFail($tenant->id);

        $this->permanentlyDelete($scopedTenant);

        return $this->success(null, 'Tenant deleted.');
    }
}
