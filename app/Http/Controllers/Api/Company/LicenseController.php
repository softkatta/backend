<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\ProductIntegration;
use App\Services\CompanyLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(
        protected CompanyLicenseService $companyLicenseService,
    ) {}

    public function activate(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => ['required', 'string'],
            'installation_id' => ['nullable', 'uuid'],
        ]);

        $result = $this->companyLicenseService->activate($this->integration($request), $request);

        return $this->respond($result);
    }

    public function verify(Request $request): JsonResponse
    {
        $result = $this->companyLicenseService->verify($this->integration($request), $request);

        return $this->respond($result);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => ['required', 'string'],
            'installation_id' => ['nullable', 'uuid'],
        ]);

        $result = $this->companyLicenseService->refreshToken($this->integration($request), $request);

        return $this->respond($result);
    }

    public function modules(Request $request): JsonResponse
    {
        $result = $this->companyLicenseService->modules($this->integration($request), $request);

        return $this->respond($result);
    }

    public function limits(Request $request): JsonResponse
    {
        $result = $this->companyLicenseService->limits($this->integration($request), $request);

        return $this->respond($result);
    }

    public function addons(Request $request): JsonResponse
    {
        $result = $this->companyLicenseService->addons($this->integration($request), $request);

        return $this->respond($result);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $result = $this->companyLicenseService->heartbeat($this->integration($request), $request);

        return $this->respond($result);
    }

    private function integration(Request $request): ProductIntegration
    {
        /** @var ProductIntegration $integration */
        $integration = $request->attributes->get('product_integration');

        return $integration;
    }

    /**
     * @param  array{success: bool, data?: array<string, mixed>, error_code?: string, message?: string, http_status: int}  $result
     */
    private function respond(array $result): JsonResponse
    {
        $status = $result['http_status'] ?? (($result['success'] ?? false) ? 200 : 403);

        if ($result['success'] ?? false) {
            return response()->json([
                'success' => true,
                'data' => $result['data'] ?? [],
            ], $status);
        }

        return response()->json([
            'success' => false,
            'error_code' => $result['error_code'] ?? 'INVALID_LICENSE',
            'message' => $result['message'] ?? 'Request failed.',
        ], $status);
    }
}
