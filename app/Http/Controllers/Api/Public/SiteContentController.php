<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Testimonial;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SiteContentController extends BaseApiController
{
    public function heroSlides(): JsonResponse
    {
        return $this->success($this->loadContent('hero_slides', function () {
            return HeroSlide::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->all();
        }));
    }

    public function testimonials(): JsonResponse
    {
        return $this->success($this->loadContent('testimonials', function () {
            return Testimonial::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->all();
        }));
    }

    public function faqs(): JsonResponse
    {
        return $this->success($this->loadContent('faqs', function () {
            return Faq::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->all();
        }));
    }

    /**
     * @param  callable(): array<int, mixed>  $query
     * @return array<int, mixed>
     */
    private function loadContent(string $configKey, callable $query): array
    {
        try {
            $table = match ($configKey) {
                'hero_slides' => 'hero_slides',
                'testimonials' => 'testimonials',
                'faqs' => 'faqs',
                default => null,
            };

            if ($table === null || ! Schema::hasTable($table)) {
                return [];
            }

            $items = $query();

            return count($items) > 0 ? $items : [];
        } catch (QueryException|Throwable) {
            return [];
        }
    }
}
