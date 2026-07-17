<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\SecurityService;
use App\Services\TenantService;
use App\Support\AdminStaffDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseApiController
{
    public function index(Request $request, SecurityService $security): JsonResponse
    {
        $query = User::with(['tenant', 'employeeProfile:id,user_id,employee_code,designation,department,company_role_id', 'employeeProfile.companyRole:id,name,slug']);

        if ($this->isHrReadOnlyRequest($request)) {
            $query->whereIn('role', [UserRole::HrManager->value, UserRole::Employee->value]);
        }

        if ($request->boolean('staff_directory')) {
            AdminStaffDirectory::applyScope($query);

            if ($request->filled('staff_role')) {
                AdminStaffDirectory::applyRoleFilter($query, $request->string('staff_role'));
            }

            if ($request->filled('search')) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', filter_var($request->string('is_active'), FILTER_VALIDATE_BOOLEAN));
            }

            return $this->success($this->paginateWithLoginDetails($request, $query->latest()->paginate($request->integer('per_page', 50))));
        }

        if ($request->boolean('customers_only')) {
            $query->where('role', UserRole::Client);
            $this->applyCustomerWorkspaceScope($request, $query, $security);

            if ($request->filled('search')) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('company_name', 'like', $term);
                });
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', filter_var($request->string('is_active'), FILTER_VALIDATE_BOOLEAN));
            }

            return $this->success($this->paginateWithLoginDetails($request, $query->latest()->paginate($request->integer('per_page', 50))));
        }

        if ($request->boolean('internal') || $request->string('exclude_role') === 'client') {
            $query->where('role', '!=', UserRole::Client);

            if ($request->filled('role')) {
                $query->where('role', $request->string('role'));
            }

            if ($request->filled('search')) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            }

            return $this->success($query->latest()->paginate($request->integer('per_page', 20)));
        }

        if ($request->boolean('all')) {
            $demoTenantId = $security->demoTenantId();
            $workspace = $security->adminWorkspaceMode($request);

            if ($demoTenantId) {
                $query->where(function ($inner) use ($workspace, $demoTenantId): void {
                    $inner->whereIn('role', [
                        UserRole::SuperAdmin->value,
                        UserRole::HrManager->value,
                        UserRole::Employee->value,
                    ])->orWhere(function ($clientQuery) use ($workspace, $demoTenantId): void {
                        $clientQuery->where('role', UserRole::Client->value);
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

            if ($request->filled('role')) {
                $query->where('role', $request->string('role'));
            }

            if ($request->filled('search')) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('company_name', 'like', $term);
                });
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', filter_var($request->string('is_active'), FILTER_VALIDATE_BOOLEAN));
            }

            return $this->success($this->paginateWithLoginDetails($request, $query->latest()->paginate($request->integer('per_page', 50))));
        }

        if ($request->filled('role') && in_array($request->string('role'), [UserRole::HrManager->value, UserRole::Employee->value], true)) {
            $query->where('role', $request->string('role'));

            return $this->success($query->latest()->paginate(20));
        }

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
            'initial_login_password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'role' => UserRole::Client,
            'two_factor_email_enabled' => $security->twoFactorLoginEnabled(),
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
        if ($this->isHrReadOnlyRequest($request) && ! in_array($user->role, [UserRole::HrManager, UserRole::Employee], true)) {
            abort(404);
        }

        if (! $this->isInternalStaffUser($user) && $user->role !== UserRole::SuperAdmin && ! $security->isTenantAllowedForAdminWorkspace($user->tenant_id, $request)) {
            abort(404);
        }

        return $this->success($this->appendLoginDetails($request, $user->load(['tenant', 'roles', 'subscriptions', 'employeeProfile'])));
    }

    public function update(Request $request, User $user, SecurityService $security): JsonResponse
    {
        if (! $this->isInternalStaffUser($user) && $user->role !== UserRole::SuperAdmin && ! $security->isTenantAllowedForAdminWorkspace($user->tenant_id, $request)) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'role' => ['sometimes', 'string', 'in:super_admin,client,hr_manager,employee'],
            'tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
        ]);

        if (array_key_exists('tenant_id', $data) && ! $security->isTenantAllowedForAdminWorkspace($data['tenant_id'], $request)) {
            return $this->error('Selected tenant is not available in the current workspace.', 422);
        }

        $user->update($data);

        return $this->success($user->fresh()->load(['tenant', 'employeeProfile']), 'User updated.');
    }

    public function destroy(Request $request, User $user, SecurityService $security, EmployeeService $employeeService): JsonResponse
    {
        if ($user->role === UserRole::SuperAdmin) {
            return $this->error('Admin accounts cannot be deleted.', 422);
        }

        if (! $this->isInternalStaffUser($user) && ! $security->isTenantAllowedForAdminWorkspace($user->tenant_id, $request)) {
            abort(404);
        }

        if ($request->user()->id === $user->id) {
            return $this->error('You cannot delete your own account.', 422);
        }

        if ($user->role === UserRole::Employee) {
            $employee = $user->employeeProfile;
            if ($employee) {
                if ($request->user()?->role === UserRole::HrManager) {
                    return $this->error('Only super admin can delete employees.', 403);
                }

                $employeeService->delete($employee);

                return $this->success(null, 'Employee deleted.');
            }
        }

        $this->permanentlyDelete($user);

        return $this->success(null, 'User deleted.');
    }

    private function isInternalStaffUser(User $user): bool
    {
        return in_array($user->role, [UserRole::HrManager, UserRole::Employee], true);
    }

    private function isHrReadOnlyRequest(Request $request): bool
    {
        $actor = $request->user();

        return $actor !== null
            && $actor->role === UserRole::HrManager
            && ! $actor->isSuperAdmin();
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, User>  $paginator
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, User>
     */
    private function paginateWithLoginDetails(Request $request, $paginator)
    {
        if ($request->user()?->isSuperAdmin()) {
            $paginator->setCollection(
                $paginator->getCollection()->map(fn (User $user) => $this->appendLoginDetails($request, $user)),
            );
        }

        return $paginator;
    }

    private function appendLoginDetails(Request $request, User $user): User
    {
        if (! $request->user()?->isSuperAdmin()) {
            return $user;
        }

        $user->setAttribute('login_details', [
            'portal_url' => $user->role->loginPortalPath(),
            'email' => $user->email,
            'password' => $user->initial_login_password,
        ]);

        return $user;
    }

    /**
     * @param  Builder<\App\Models\User>  $query
     */
    private function applyCustomerWorkspaceScope(Request $request, $query, SecurityService $security): void
    {
        $demoTenantId = $security->demoTenantId();
        if (! $demoTenantId) {
            return;
        }

        $workspace = $security->adminWorkspaceMode($request);
        if ($workspace === 'demo') {
            $query->where('tenant_id', $demoTenantId);

            return;
        }

        $query->where(function ($liveQuery) use ($demoTenantId): void {
            $liveQuery->whereNull('tenant_id')
                ->orWhere('tenant_id', '!=', $demoTenantId);
        });
    }
}
