<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(Testimonial::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'rating' => ['integer', 'min:1', 'max:5'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        return $this->success(Testimonial::create($data), 'Testimonial created.', 201);
    }

    public function update(Request $request, Testimonial $testimonial): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'rating' => ['integer', 'min:1', 'max:5'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $testimonial->update($data);

        return $this->success($testimonial->fresh(), 'Testimonial updated.');
    }

    public function destroy(Testimonial $testimonial): JsonResponse
    {
        $this->permanentlyDelete($testimonial);

        return $this->success(null, 'Testimonial deleted.');
    }
}
