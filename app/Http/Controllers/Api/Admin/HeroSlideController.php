<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\HeroSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HeroSlideController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(HeroSlide::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'image' => ['required', 'string', 'max:500'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $slide = HeroSlide::create($data);

        return $this->success($slide, 'Hero slide created.', 201);
    }

    public function update(Request $request, HeroSlide $heroSlide): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'image' => ['sometimes', 'string', 'max:500'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $heroSlide->update($data);

        return $this->success($heroSlide->fresh(), 'Hero slide updated.');
    }

    public function destroy(HeroSlide $heroSlide): JsonResponse
    {
        if ($heroSlide->image && ! str_starts_with($heroSlide->image, '/') && ! str_starts_with($heroSlide->image, 'http')) {
            Storage::disk('public')->delete($heroSlide->image);
        }

        $heroSlide->delete();

        return $this->success(null, 'Hero slide deleted.');
    }
}
