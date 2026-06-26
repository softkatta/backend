<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            Tenant::with('owner:id,name,email')->latest()->paginate(20)
        );
    }

    public function store(Request $request, TenantService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:tenants,slug'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:active,suspended,inactive'],
            'settings' => ['nullable', 'array'],
        ]);

        $tenant = $service->create($data);

        return $this->success($tenant, 'Tenant created.', 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return $this->success($tenant->load(['owner', 'users', 'subscriptions']));
    }

    public function update(Request $request, Tenant $tenant, TenantService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:tenants,slug,'.$tenant->id],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain,'.$tenant->id],
            'status' => ['nullable', 'string', 'in:active,suspended,inactive'],
            'settings' => ['nullable', 'array'],
        ]);

        $tenant = $service->update($tenant, $data);

        return $this->success($tenant, 'Tenant updated.');
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return $this->success(null, 'Tenant deleted.');
    }
}
