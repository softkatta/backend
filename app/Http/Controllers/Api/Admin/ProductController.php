<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProductController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            Product::with(['category', 'plans', 'features'])->orderBy('sort_order')->paginate(20)
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $features = $data['features'] ?? null;
        $screenshot = array_key_exists('screenshot', $data) ? $data['screenshot'] : null;
        $demoVideo = array_key_exists('demo_video_url', $data) ? $data['demo_video_url'] : null;
        unset($data['features'], $data['screenshot'], $data['demo_video_url']);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $product = Product::create($data);
        $this->syncFeatures($product, $features);
        $this->syncMedia($product, $screenshot, $demoVideo);

        return $this->success($product->load(['features', 'screenshots', 'videos']), 'Product created.', 201);
    }

    public function show(Product $product): JsonResponse
    {
        return $this->success($product->load(['category', 'features', 'screenshots', 'videos', 'plans']));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $features = $data['features'] ?? null;
        $screenshot = array_key_exists('screenshot', $data) ? $data['screenshot'] : null;
        $demoVideo = array_key_exists('demo_video_url', $data) ? $data['demo_video_url'] : null;
        unset($data['features'], $data['screenshot'], $data['demo_video_url']);
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $product->update($data);
        if ($features !== null) {
            $this->syncFeatures($product, $features);
        }
        if ($screenshot !== null || $demoVideo !== null) {
            $this->syncMedia($product, $screenshot, $demoVideo);
        }

        return $this->success($product->fresh()->load(['category', 'features', 'screenshots', 'videos', 'plans']), 'Product updated.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->permanentlyDelete($product);

        return $this->success(null, 'Product deleted.');
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $features
     */
    private function syncFeatures(Product $product, ?array $features): void
    {
        if ($features === null) {
            return;
        }

        $product->features()->delete();

        foreach ($features as $index => $feature) {
            if (empty($feature['title'])) {
                continue;
            }

            $product->features()->create([
                'title' => $feature['title'],
                'description' => $feature['description'] ?? null,
                'icon' => $feature['icon'] ?? null,
                'sort_order' => $feature['sort_order'] ?? $index,
            ]);
        }
    }

    private function syncMedia(Product $product, ?string $screenshot, ?string $demoVideo): void
    {
        if ($screenshot !== null) {
            $product->screenshots()->delete();
            if ($screenshot !== '') {
                $product->screenshots()->create([
                    'image_path' => $screenshot,
                    'sort_order' => 0,
                ]);
            }
        }

        if ($demoVideo !== null) {
            $product->videos()->delete();
            if ($demoVideo !== '') {
                $product->videos()->create([
                    'title' => 'Product demo',
                    'video_url' => $demoVideo,
                    'sort_order' => 0,
                ]);
            }
        }
    }
}
