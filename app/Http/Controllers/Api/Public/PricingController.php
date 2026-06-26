<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;

class PricingController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $products = Product::with(['plans' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $faqs = Faq::where('is_active', true)
            ->where('category', 'pricing')
            ->orderBy('sort_order')
            ->get();

        $testimonials = Testimonial::where('is_active', true)
            ->orderBy('sort_order')
            ->limit(6)
            ->get();

        return $this->success([
            'products' => $products,
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
            'faqs' => $faqs,
            'testimonials' => $testimonials,
        ]);
    }
}
