<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Plan::with('product')->orderBy('sort_order');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        $perPage = min(200, max(1, $request->integer('per_page', 100)));

        return $this->success($query->paginate($perPage));
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $plan = Plan::create($data);

        return $this->success($plan, 'Plan created.', 201);
    }

    public function show(Plan $plan): JsonResponse
    {
        return $this->success($plan->load('product'));
    }

    public function update(StorePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan->update($request->validated());

        return $this->success($plan->fresh(), 'Plan updated.');
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->permanentlyDelete($plan);

        return $this->success(null, 'Plan deleted.');
    }
}
