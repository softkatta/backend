<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Models\User;
use App\Services\SecurityService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseApiController
{
    public function index(Request $request, SecurityService $security): JsonResponse
    {
        $query = User::with('tenant');

        $demoTenantId = $security->demoTenantId();
        $workspace = $security->adminWorkspaceMode($request);

        if ($demoTenantId) {
            $query->where(function ($inner) use ($workspace, $demoTenantId): void {
                $inner->where('role', UserRole::SuperAdmin->value)
                    ->orWhere(function ($clientQuery) use ($workspace, $demoTenantId): void {
                        if ($workspace === 'demo') {
                            $clientQuery->where('tenant_id', $demoTenantId);

                            return;
                        }

                        $clientQuery->where(function ($liveQuery) use ($demoTenantId): void {
                            $liveQuery->whereNull('tenant_id')
                                ->orWhere('tenant_id', '!=', $demoTenantId);
                        });
                    });
            });
        }

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        return $this->success($query->latest()->paginate(20));
    }

    public function store(StoreCustomerRequest $request, TenantService $tenantService, SecurityService $security): JsonResponse
    {
        $validated = $request->validated();
        $workspace = $security->adminWorkspaceMode($request);
        $demoTenantId = $security->demoTenantId();

        if ($workspace === 'demo' && ! $demoTenantId) {
            return $this->error('Demo tenant is not configured. Set demo account first.', 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'role' => UserRole::Client,
            'two_factor_email_enabled' => true,
            'is_active' => $validated['is_active'] ?? true,
            'tenant_id' => $workspace === 'demo' ? $demoTenantId : null,
        ]);

        $user->assignRole('client');

        if ($workspace !== 'demo') {
            $tenant = $tenantService->create([
                'name' => $validated['company_name'] ?? $validated['name'].' Workspace',
            ], $user);

            $user->update(['tenant_id' => $tenant->id]);
        }

        return $this->success($user->fresh()->load('tenant'), 'Customer created.', 201);
    }

    public function show(Request $request, User $user, SecurityService $security): JsonResponse
    {
        if ($user->role !== UserRole::SuperAdmin && ! $security->isTenantAllowedForAdminWorkspace($user->tenant_id, $request)) {
            abort(404);
        }

        return $this->success($user->load(['tenant', 'roles', 'subscriptions']));
    }

    public function update(Request $request, User $user, SecurityService $security): JsonResponse
    {
        if ($user->role !== UserRole::SuperAdmin && ! $security->isTenantAllowedForAdminWorkspace($user->tenant_id, $request)) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'role' => ['sometimes', 'string', 'in:super_admin,client'],
            'tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
        ]);

        if (array_key_exists('tenant_id', $data) && ! $security->isTenantAllowedForAdminWorkspace($data['tenant_id'], $request)) {
            return $this->error('Selected tenant is not available in the current workspace.', 422);
        }

        $user->update($data);

        return $this->success($user->fresh()->load('tenant'), 'User updated.');
    }

    public function destroy(Request $request, User $user, SecurityService $security): JsonResponse
    {
        if ($user->role === UserRole::SuperAdmin) {
            return $this->error('Admin accounts cannot be deleted.', 422);
        }

        if (! $security->isTenantAllowedForAdminWorkspace($user->tenant_id, $request)) {
            abort(404);
        }

        if ($request->user()->id === $user->id) {
            return $this->error('You cannot delete your own account.', 422);
        }

        $this->permanentlyDelete($user);

        return $this->success(null, 'Customer deleted.');
    }
}
