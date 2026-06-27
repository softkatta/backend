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
    public function index(): JsonResponse
    {
        return $this->success(Plan::with('product')->orderBy('sort_order')->paginate(20));
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
