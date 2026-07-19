<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Tenant;
use App\Services\SecurityService;
use App\Services\TenantDomainRequestService;
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

    public function pendingDomains(TenantDomainRequestService $domainRequests): JsonResponse
    {
        return $this->success($domainRequests->listPending());
    }

    public function approvePendingDomain(
        Request $request,
        Tenant $tenant,
        int $subscription,
        TenantDomainRequestService $domainRequests,
        SecurityService $security,
    ): JsonResponse {
        $query = Tenant::query();
        $security->applyAdminWorkspaceScope($query, $request, 'id');
        $scopedTenant = $query->findOrFail($tenant->id);

        $status = $domainRequests->approve($scopedTenant, $subscription, $request->user());

        return $this->success($status, 'Domains approved. License keys will generate if eligible.');
    }

    public function rejectPendingDomain(
        Request $request,
        Tenant $tenant,
        int $subscription,
        TenantDomainRequestService $domainRequests,
        SecurityService $security,
    ): JsonResponse {
        $query = Tenant::query();
        $security->applyAdminWorkspaceScope($query, $request, 'id');
        $scopedTenant = $query->findOrFail($tenant->id);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $status = $domainRequests->reject(
            $scopedTenant,
            $subscription,
            $request->user(),
            $data['reason'] ?? null,
        );

        return $this->success($status, 'Domain request rejected. Customer can resubmit.');
    }

    public function store(Request $request, TenantService $service, SecurityService $security): JsonResponse
    {
        if ($security->adminWorkspaceMode($request) === 'demo') {
            return $this->error('New tenants cannot be created in demo workspace mode.', 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:tenants,slug'],
            'owner_id' => ['required', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:active,suspended,inactive'],
            'settings' => ['nullable', 'array'],
            'subscription_domains' => ['nullable', 'array'],
            'subscription_domains.*.subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
            'subscription_domains.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'subscription_domains.*.plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'subscription_domains.*.frontend_domain' => ['required', 'string', 'max:255'],
            'subscription_domains.*.backend_domain' => ['required', 'string', 'max:255'],
        ]);

        $tenant = $service->create($data);

        return $this->success(
            $tenant->load('owner:id,name,email'),
            'Tenant created. License keys generate automatically when subscription domains are saved.',
            201
        );
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
            'owner_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:active,suspended,inactive'],
            'settings' => ['nullable', 'array'],
            'subscription_domains' => ['nullable', 'array'],
            'subscription_domains.*.subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
            'subscription_domains.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'subscription_domains.*.plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'subscription_domains.*.frontend_domain' => ['required', 'string', 'max:255'],
            'subscription_domains.*.backend_domain' => ['required', 'string', 'max:255'],
        ]);

        $tenant = $service->update($scopedTenant, $data);
        $assigned = count((array) data_get($tenant->settings, 'subscription_domains', []));

        return $this->success(
            $tenant->load('owner:id,name,email'),
            $assigned > 0
                ? 'Tenant updated. License keys generated/synced for each subscription with domains.'
                : 'Tenant updated.'
        );
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
