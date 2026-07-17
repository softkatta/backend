<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CompanyRole;
use App\Services\CompanyRoleMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyRoleController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = CompanyRole::withCount('employees')
            ->with(['roleMenus.portalMenu'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return $this->success($query->get()->map(fn (CompanyRole $role) => $this->formatRole($role))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateRolePayload($request);
        $menus = $data['employee_portal_menus'] ?? null;
        unset($data['employee_portal_menus']);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $role = CompanyRole::create($data);

        if (array_key_exists('employee_portal_menus', $request->all())) {
            CompanyRoleMenuService::syncRoleMenus($role, $menus);
        }

        return $this->success($this->formatRole($role->fresh(['roleMenus.portalMenu'])), 'Company role created.', 201);
    }

    public function show(CompanyRole $companyRole): JsonResponse
    {
        $companyRole->loadCount('employees');
        $companyRole->load(['roleMenus.portalMenu']);

        return $this->success($this->formatRole($companyRole));
    }

    public function update(Request $request, CompanyRole $companyRole): JsonResponse
    {
        $data = $this->validateRolePayload($request, $companyRole->id);
        $hasMenus = array_key_exists('employee_portal_menus', $request->all());
        $menus = $data['employee_portal_menus'] ?? null;
        unset($data['employee_portal_menus']);

        if (array_key_exists('name', $data) && ! array_key_exists('slug', $data)) {
            $data['slug'] = Str::slug($data['name']);
        }

        $companyRole->update($data);

        if ($hasMenus) {
            CompanyRoleMenuService::syncRoleMenus($companyRole->fresh(), $menus);
        }

        return $this->success(
            $this->formatRole($companyRole->fresh(['roleMenus.portalMenu'])->loadCount('employees')),
            'Company role updated.',
        );
    }

    public function updateMenus(Request $request, CompanyRole $companyRole): JsonResponse
    {
        $data = $request->validate([
            'employee_portal_menus' => ['nullable', 'array'],
            'employee_portal_menus.*' => ['string', Rule::in(CompanyRoleMenuService::allMenuKeys())],
        ]);

        $menus = $data['employee_portal_menus'] ?? null;
        if ($menus === []) {
            $menus = null;
        } elseif (is_array($menus)) {
            $menus = CompanyRoleMenuService::sanitizeMenuKeys($menus);
        }

        CompanyRoleMenuService::syncRoleMenus($companyRole, $menus);

        return $this->success(
            $this->formatRole($companyRole->fresh(['roleMenus.portalMenu'])->loadCount('employees')),
            'Company role menus updated.',
        );
    }

    public function destroy(CompanyRole $companyRole): JsonResponse
    {
        if ($companyRole->employees()->exists()) {
            return $this->error('This role is assigned to employees and cannot be deleted.', 422);
        }

        $this->permanentlyDelete($companyRole);

        return $this->success(null, 'Company role deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRole(CompanyRole $role): array
    {
        $override = $role->employee_portal_menus;
        $menuKeys = CompanyRoleMenuService::menuKeysFor($role->slug, $role->category, $override, $role);
        $defaultKeys = CompanyRoleMenuService::defaultMenuKeysFor($role->slug, $role->category);

        $normalizedCurrent = CompanyRoleMenuService::sanitizeMenuKeys($menuKeys);
        $normalizedDefault = CompanyRoleMenuService::sanitizeMenuKeys($defaultKeys);
        sort($normalizedCurrent);
        sort($normalizedDefault);
        $usesDefault = $normalizedCurrent === $normalizedDefault;

        $menuLabels = collect(CompanyRoleMenuService::menuCatalog())
            ->whereIn('key', $menuKeys)
            ->pluck('label')
            ->values()
            ->all();

        return [
            ...$role->toArray(),
            'employee_portal_menus' => $menuKeys,
            'employee_portal_menus_override' => $usesDefault ? null : ($override ?: $menuKeys),
            'uses_default_portal_menus' => $usesDefault,
            'employee_portal_paths' => CompanyRoleMenuService::pathsFor($role->slug, $role->category, $override, $role),
            'employee_portal_menu_labels' => $menuLabels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRolePayload(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = Rule::unique('company_roles', 'slug');
        if ($ignoreId) {
            $slugRule = $slugRule->ignore($ignoreId);
        }

        $rules = [
            'name' => [$ignoreId ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $slugRule],
            'category' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'employee_portal_menus' => ['nullable', 'array'],
            'employee_portal_menus.*' => ['string', Rule::in(CompanyRoleMenuService::allMenuKeys())],
        ];

        $data = $request->validate($rules);

        if (array_key_exists('employee_portal_menus', $data)) {
            $data['employee_portal_menus'] = $this->normalizePortalMenus($data['employee_portal_menus']);
        }

        return $data;
    }

    /**
     * @param  list<string>|null  $menus
     * @return list<string>|null
     */
    private function normalizePortalMenus(?array $menus): ?array
    {
        if ($menus === null || $menus === []) {
            return null;
        }

        return CompanyRoleMenuService::sanitizeMenuKeys($menus);
    }
}
