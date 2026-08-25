<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\PurchaseService;
use App\Services\SecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $security = app(SecurityService::class);
        $restrictToDemoTenant = $security->isDemoAccount($user) && ! empty($user->tenant_id);

        $subscriptionQuery = Subscription::withoutGlobalScope('tenant')
            ->where('user_id', $user->id);

        if ($restrictToDemoTenant) {
            $subscriptionQuery->where('tenant_id', $user->tenant_id);
        }

        $subscriptionProductIds = $subscriptionQuery->pluck('product_id')
            ->unique()
            ->values();

        $ordersQuery = Order::withoutGlobalScope('tenant')
            ->where('user_id', $user->id)
            ->where('status', 'completed');

        if ($restrictToDemoTenant) {
            $ordersQuery->where('tenant_id', $user->tenant_id);
        }

        $orderedProductIds = $ordersQuery->pluck('product_id')
            ->unique()
            ->values();

        $purchasedProductIds = $subscriptionProductIds
            ->merge($orderedProductIds)
            ->filter()
            ->unique()
            ->values();

        if ($purchasedProductIds->isEmpty()) {
            return $this->success([]);
        }

        $products = Product::with([
            'category',
            'screenshots',
            'plans' => fn ($q) => $q->where('is_active', true),
        ])
            ->whereIn('id', $purchasedProductIds)
            ->orderBy('sort_order')
            ->get();

        return $this->success($products);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $isPurchased = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('product', fn ($query) => $query->where('slug', $slug))
            ->exists();

        if (! $isPurchased) {
            return $this->error('Unauthorized.', 403);
        }

        $product = Product::with(['features', 'screenshots', 'videos', 'plans'])
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->success($product);
    }

    public function download(Request $request, string $slug, string $platform): BinaryFileResponse|RedirectResponse|JsonResponse
    {
        $platform = strtolower($platform);
        if (! in_array($platform, ['android', 'windows'], true)) {
            return $this->error('Unsupported download platform.', 404);
        }

        $product = Product::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $userId = $request->user()->id;
        $hasSubscription = Subscription::withoutGlobalScope('tenant')
            ->where('user_id', $userId)
            ->where('product_id', $product->id)
            ->whereIn('status', ['active', 'trial', 'expiring_soon'])
            ->exists();
        $hasCompletedOrder = Order::withoutGlobalScope('tenant')
            ->where('user_id', $userId)
            ->where('product_id', $product->id)
            ->where('status', 'completed')
            ->exists();

        if (! $hasSubscription && ! $hasCompletedOrder) {
            return $this->error('An active product entitlement is required to download this app.', 403);
        }

        $release = data_get($product->meta, "releases.{$platform}");
        $path = is_array($release) ? trim((string) ($release['file_path'] ?? '')) : '';
        if ($path === '') {
            return $this->error('No download is currently available for this platform.', 404);
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return redirect()->away($path);
        }

        $path = ltrim($path, '/');
        if (! Storage::disk('public')->exists($path)) {
            return $this->error('The release file is unavailable. Contact Softkatta support.', 404);
        }

        $fallback = $platform === 'android' ? "{$product->slug}.apk" : "{$product->slug}-setup.exe";
        $fileName = basename((string) ($release['file_name'] ?? $fallback));

        return response()->download(Storage::disk('public')->path($path), $fileName);
    }
    public function startTrial(Request $request, string $slug, PurchaseService $purchaseService): JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        if (! $product->has_free_trial) {
            return $this->error('This product does not offer a free trial.', 422);
        }

        // Prevent multiple trials per user for the same product
        $already = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->whereNotNull('trial_ends_at')
            ->exists();

        if ($already) {
            return $this->error('A free trial has already been used for your account.', 422);
        }

        // A trial belongs to the product, not to a paid plan. A plan is selected
        // only when the customer converts after the trial ends.
        $result = $purchaseService->startTrialForExistingUser($request->user(), $product);

        return $this->success($result, 'Free trial started. You can use the product for the trial period.');
    }
}
