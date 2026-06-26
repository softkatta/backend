<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Blog;
use Illuminate\Http\JsonResponse;

class BlogController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $blogs = Blog::with('author:id,name,avatar')
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(12);

        return $this->success($blogs);
    }

    public function show(string $slug): JsonResponse
    {
        $blog = Blog::with('author:id,name,avatar')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return $this->success($blog);
    }
}
