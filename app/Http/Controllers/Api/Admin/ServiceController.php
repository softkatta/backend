<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(Service::orderBy('sort_order')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:services,slug'],
            'description' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'bullets_heading' => ['nullable', 'string', 'max:255'],
            'bullets' => ['nullable', 'array'],
            'bullets.*' => ['string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $service = Service::create($data);

        return $this->success($service, 'Service created.', 201);
    }

    public function show(Service $service): JsonResponse
    {
        return $this->success($service);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:services,slug,'.$service->id],
            'description' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'bullets_heading' => ['nullable', 'string', 'max:255'],
            'bullets' => ['nullable', 'array'],
            'bullets.*' => ['string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $service->update($data);

        return $this->success($service->fresh(), 'Service updated.');
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->permanentlyDelete($service);

        return $this->success(null, 'Service deleted.');
    }
}
