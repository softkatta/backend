<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CompanyAsset;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyAssetController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = CompanyAsset::query()
            ->with(['assignee:id,full_name,employee_code,email', 'creator:id,name'])
            ->latest('id');

        if ($request->filled('status') && $request->string('status') !== 'all') {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('category') && $request->string('category') !== 'all') {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('asset_tag', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('serial_number', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('model', 'like', $term);
            });
        }

        return $this->success($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;
        $data = $this->applyAssignmentRules($data);

        $asset = CompanyAsset::create($data);

        return $this->success(
            $asset->load(['assignee:id,full_name,employee_code,email', 'creator:id,name']),
            'Asset created.',
            201,
        );
    }

    public function show(CompanyAsset $company_asset): JsonResponse
    {
        return $this->success(
            $company_asset->load(['assignee:id,full_name,employee_code,email', 'creator:id,name']),
        );
    }

    public function update(Request $request, CompanyAsset $company_asset): JsonResponse
    {
        $data = $this->validated($request, updating: true);
        $data = $this->applyAssignmentRules($data, $company_asset);

        $company_asset->update($data);

        return $this->success(
            $company_asset->fresh()->load(['assignee:id,full_name,employee_code,email', 'creator:id,name']),
            'Asset updated.',
        );
    }

    public function destroy(CompanyAsset $company_asset): JsonResponse
    {
        $this->permanentlyDelete($company_asset);

        return $this->success(null, 'Asset deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'asset_tag' => [
                $required,
                'string',
                'max:80',
                Rule::unique('company_assets', 'asset_tag')->ignore($request->route('company_asset')),
            ],
            'name' => [$required, 'string', 'max:255'],
            'category' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(CompanyAsset::CATEGORIES)],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'status' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(CompanyAsset::STATUSES)],
            'condition' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(CompanyAsset::CONDITIONS)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'purchased_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:employees,id'],
            'assigned_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyAssignmentRules(array $data, ?CompanyAsset $existing = null): array
    {
        $assignedTo = array_key_exists('assigned_to', $data)
            ? $data['assigned_to']
            : $existing?->assigned_to;

        if (! empty($assignedTo)) {
            abort_unless(Employee::query()->whereKey($assignedTo)->exists(), 422, 'Employee not found.');

            $data['status'] = $data['status'] ?? 'assigned';
            if (($data['status'] ?? null) === 'available') {
                $data['status'] = 'assigned';
            }
            if (empty($data['assigned_at']) && (! $existing || (int) $existing->assigned_to !== (int) $assignedTo)) {
                $data['assigned_at'] = now();
            }
        } else {
            if (array_key_exists('assigned_to', $data) && empty($assignedTo)) {
                $data['assigned_to'] = null;
                $data['assigned_at'] = null;
                if (($data['status'] ?? $existing?->status) === 'assigned') {
                    $data['status'] = 'available';
                }
            }
        }

        return $data;
    }
}
