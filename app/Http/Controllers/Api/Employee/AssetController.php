<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CompanyAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $assets = CompanyAsset::query()
            ->where('assigned_to', $employee->id)
            ->whereIn('status', ['assigned', 'maintenance'])
            ->latest('assigned_at')
            ->latest('id')
            ->paginate(20);

        return $this->success($assets);
    }

    public function show(Request $request, CompanyAsset $company_asset): JsonResponse
    {
        $employee = $this->employeeFor($request);
        abort_unless((int) $company_asset->assigned_to === (int) $employee->id, 404);

        return $this->success($company_asset);
    }
}
