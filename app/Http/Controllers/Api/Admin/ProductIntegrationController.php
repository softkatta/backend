<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\LicenseApiLog;
use App\Models\LicenseDomainResetRequest;
use App\Models\Product;
use App\Models\ProductIntegration;
use App\Services\LicenseService;
use App\Services\ProductIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductIntegrationController extends BaseApiController
{
    public function __construct(
        protected ProductIntegrationService $integrationService,
        protected LicenseService $licenseService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ProductIntegration::with('product')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('public_api_key', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $items = $query->paginate(20);
        $items->getCollection()->transform(
            fn (ProductIntegration $integration): array => $this->integrationService->toAdminArray($integration)
        );

        return $this->success($items);
    }

    public function show(ProductIntegration $productIntegration): JsonResponse
    {
        return $this->success(
            $this->integrationService->toAdminArray($productIntegration->load('product'), true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'version' => ['nullable', 'string', 'max:32'],
            'api_base_url' => ['nullable', 'url', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if ($product->productIntegration) {
            return $this->error('An integration already exists for this product.', 409);
        }

        $integration = $this->integrationService->createForProduct($product, $validated);

        return $this->success(
            $this->integrationService->toAdminArray($integration->fresh(['product']), true),
            'Product integration created.',
            201
        );
    }

    public function update(Request $request, ProductIntegration $productIntegration): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:120'],
            'version' => ['sometimes', 'string', 'max:32'],
            'api_base_url' => ['sometimes', 'url', 'max:255'],
            'supported_versions' => ['nullable', 'array'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $productIntegration->update($validated);

        return $this->success(
            $this->integrationService->toAdminArray($productIntegration->fresh(['product']))
        );
    }

    public function regenerateKeys(ProductIntegration $productIntegration): JsonResponse
    {
        $integration = $this->integrationService->regenerateKeys($productIntegration);

        return $this->success(
            $this->integrationService->toAdminArray($integration, true),
            'Integration keys regenerated.'
        );
    }

    public function guide(ProductIntegration $productIntegration): JsonResponse
    {
        return $this->success(
            $this->integrationService->buildIntegrationGuide($productIntegration)
        );
    }

    public function destroy(ProductIntegration $productIntegration): JsonResponse
    {
        $this->permanentlyDelete($productIntegration);

        return $this->success(null, 'Product integration deleted.');
    }

    public function apiLogs(Request $request): JsonResponse
    {
        $query = LicenseApiLog::with(['licenseKey.user', 'productIntegration'])
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('domain', 'like', "%{$search}%")
                    ->orWhere('product_slug', 'like', "%{$search}%")
                    ->orWhere('error_code', 'like', "%{$search}%")
                    ->orWhereHas('licenseKey', fn ($q) => $q->where('license_key', 'like', "%{$search}%"));
            });
        }

        if ($request->boolean('failed_only')) {
            $query->where('success', false);
        }

        return $this->success($query->paginate(30));
    }

    public function domainResetRequests(Request $request): JsonResponse
    {
        $query = LicenseDomainResetRequest::with(['licenseKey.product', 'user'])
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return $this->success($query->paginate(20));
    }

    public function reviewDomainReset(Request $request, LicenseDomainResetRequest $domainResetRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $domainResetRequest->update([
            'status' => $validated['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($validated['status'] === 'approved') {
            $this->licenseService->resetDomains($domainResetRequest->licenseKey, $request->user()->id);
        }

        return $this->success($domainResetRequest->fresh(['licenseKey', 'user']), 'Domain reset request reviewed.');
    }
}
