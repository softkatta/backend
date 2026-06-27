<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductCategoryController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(ProductCategory::withCount('products')->orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:product_categories,slug'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category = ProductCategory::create($data);

        return $this->success($category, 'Category created.', 201);
    }

    public function show(ProductCategory $category): JsonResponse
    {
        return $this->success($category->load('products'));
    }

    public function update(Request $request, ProductCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:product_categories,slug,'.$category->id],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $category->update($data);

        return $this->success($category->fresh(), 'Category updated.');
    }

    public function destroy(ProductCategory $category): JsonResponse
    {
        $this->permanentlyDelete($category);

        return $this->success(null, 'Category deleted.');
    }
}
