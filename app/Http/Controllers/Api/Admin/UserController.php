<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('tenant');

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        return $this->success($query->latest()->paginate(20));
    }

    public function store(StoreCustomerRequest $request, TenantService $tenantService): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'role' => UserRole::Client,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->assignRole('client');

        $tenant = $tenantService->create([
            'name' => $validated['company_name'] ?? $validated['name'].' Workspace',
        ], $user);

        $user->update(['tenant_id' => $tenant->id]);

        return $this->success($user->fresh()->load('tenant'), 'Customer created.', 201);
    }

    public function show(User $user): JsonResponse
    {
        return $this->success($user->load(['tenant', 'roles', 'subscriptions']));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'role' => ['sometimes', 'string', 'in:super_admin,client'],
            'tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
        ]);

        $user->update($data);

        return $this->success($user->fresh()->load('tenant'), 'User updated.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->role === UserRole::SuperAdmin) {
            return $this->error('Admin accounts cannot be deleted.', 422);
        }

        if ($request->user()->id === $user->id) {
            return $this->error('You cannot delete your own account.', 422);
        }

        $user->delete();

        return $this->success(null, 'Customer deleted.');
    }
}
