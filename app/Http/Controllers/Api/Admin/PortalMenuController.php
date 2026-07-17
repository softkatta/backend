<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PortalMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortalMenuController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $portal = $request->string('portal')->toString() ?: 'employee';

        $query = PortalMenu::query()
            ->where('portal', $portal)
            ->orderBy('sort_order')
            ->orderBy('label');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return $this->success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateMenu($request);
        $data['portal'] = $data['portal'] ?? 'employee';

        $menu = PortalMenu::create($data);

        return $this->success($menu, 'Portal menu created.', 201);
    }

    public function show(PortalMenu $portalMenu): JsonResponse
    {
        return $this->success($portalMenu);
    }

    public function update(Request $request, PortalMenu $portalMenu): JsonResponse
    {
        $data = $this->validateMenu($request, $portalMenu->id);
        $portalMenu->update($data);

        return $this->success($portalMenu->fresh(), 'Portal menu updated.');
    }

    public function destroy(PortalMenu $portalMenu): JsonResponse
    {
        if ($portalMenu->key === 'dashboard') {
            return $this->error('The dashboard menu cannot be deleted.', 422);
        }

        $this->permanentlyDelete($portalMenu);

        return $this->success(null, 'Portal menu deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMenu(Request $request, ?int $ignoreId = null): array
    {
        $portal = $request->input('portal', 'employee');

        $keyRule = Rule::unique('portal_menus', 'key')->where(fn ($q) => $q->where('portal', $portal));
        if ($ignoreId) {
            $keyRule = $keyRule->ignore($ignoreId);
        }

        return $request->validate([
            'portal' => ['sometimes', 'string', 'max:50'],
            'key' => [$ignoreId ? 'sometimes' : 'required', 'string', 'max:80', 'alpha_dash', $keyRule],
            'label' => [$ignoreId ? 'sometimes' : 'required', 'string', 'max:255'],
            'route' => [$ignoreId ? 'sometimes' : 'required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:80'],
            'parent_key' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['integer', 'min:0'],
            'permission' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'badge_enabled' => ['boolean'],
        ]);
    }
}
