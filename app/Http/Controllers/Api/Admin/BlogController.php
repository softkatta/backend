<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreBlogRequest;
use App\Models\Blog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(Blog::with('author:id,name')->latest()->paginate(20));
    }

    public function store(StoreBlogRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['author_id'] = $request->user()->id;

        if ($data['is_published'] ?? false) {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $blog = Blog::create($data);

        return $this->success($blog, 'Blog post created.', 201);
    }

    public function show(Blog $blog): JsonResponse
    {
        return $this->success($blog->load('author'));
    }

    public function update(StoreBlogRequest $request, Blog $blog): JsonResponse
    {
        $data = $request->validated();

        if (($data['is_published'] ?? false) && ! $blog->published_at) {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $blog->update($data);

        return $this->success($blog->fresh(), 'Blog post updated.');
    }

    public function destroy(Blog $blog): JsonResponse
    {
        $this->permanentlyDelete($blog);

        return $this->success(null, 'Blog post deleted.');
    }
}
