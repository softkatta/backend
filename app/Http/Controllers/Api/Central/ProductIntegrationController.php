<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\ProductIntegration;
use App\Services\ProductLicenseValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductIntegrationController extends Controller
{
    public function __construct(
        protected ProductLicenseValidationService $validationService,
    ) {}

    public function check(Request $request): JsonResponse
    {
        $integration = $this->integration($request);
        $validated = $request->validate([
            'license_key' => ['nullable', 'string'],
            'domain' => ['nullable', 'string', 'max:255'],
            'product_version' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $this->validationService->check(
            $integration,
            $request,
            $validated['license_key'] ?? null,
            $validated['domain'] ?? null,
            $validated['product_version'] ?? null,
        );

        return response()->json($result, ($result['status'] ?? false) ? 200 : 403);
    }

    public function subscription(Request $request): JsonResponse
    {
        $result = $this->validationService->subscription($this->integration($request), $request);

        return response()->json($result, ($result['status'] ?? false) ? 200 : 403);
    }

    public function modules(Request $request): JsonResponse
    {
        $result = $this->validationService->modules($this->integration($request), $request);

        return response()->json($result, ($result['status'] ?? false) ? 200 : 403);
    }

    public function limits(Request $request): JsonResponse
    {
        $result = $this->validationService->limits($this->integration($request), $request);

        return response()->json($result, ($result['status'] ?? false) ? 200 : 403);
    }

    public function addons(Request $request): JsonResponse
    {
        $result = $this->validationService->addons($this->integration($request), $request);

        return response()->json($result, ($result['status'] ?? false) ? 200 : 403);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $result = $this->validationService->heartbeat($this->integration($request), $request);

        return response()->json($result, ($result['status'] ?? false) ? 200 : 403);
    }

    public function activate(Request $request): JsonResponse
    {
        $integration = $this->integration($request);
        $validated = $request->validate([
            'license_key' => ['required', 'string'],
            'domain' => ['required', 'string', 'max:255'],
            'product_version' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $this->validationService->activateLicense(
            $integration,
            $request,
            $validated['license_key'],
            $validated['domain'],
            $validated['product_version'] ?? null,
        );

        return response()->json($result, ($result['status'] ?? false) ? 200 : 422);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string'],
            'domain' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->validationService->deactivateLicense(
            $this->integration($request),
            $request,
            $validated['license_key'],
            $validated['domain'] ?? null,
        );

        return response()->json($result, ($result['status'] ?? false) ? 200 : 422);
    }

    private function integration(Request $request): ProductIntegration
    {
        /** @var ProductIntegration $integration */
        $integration = $request->attributes->get('product_integration');

        return $integration;
    }
}
