<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreCareerRequest;
use App\Models\Career;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CareerController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            Career::with('companyRole:id,name,category')
                ->orderBy('sort_order')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    public function store(StoreCareerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['employment_type'] = $data['employment_type'] ?? 'full-time';

        if ($data['is_published'] ?? false) {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $career = Career::create($data);

        return $this->success($career->load('companyRole:id,name,category'), 'Career opening created.', 201);
    }

    public function show(Career $career): JsonResponse
    {
        return $this->success($career->load('companyRole:id,name,category'));
    }

    public function update(StoreCareerRequest $request, Career $career): JsonResponse
    {
        $data = $request->validated();

        if (($data['is_published'] ?? false) && ! $career->published_at) {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $career->update($data);

        return $this->success($career->fresh(['companyRole:id,name,category']), 'Career opening updated.');
    }

    public function destroy(Career $career): JsonResponse
    {
        $this->permanentlyDelete($career);

        return $this->success(null, 'Career opening deleted.');
    }
}
