<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\LicenseKey;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central License API — consumed by all SoftKatta products.
 *
 * All endpoints are rate-limited (60/min) and require no user auth;
 * the license key itself is the credential.
 */
class LicenseController extends BaseApiController
{
    public function __construct(private readonly LicenseService $licenseService) {}

    private function ensureCentralMutationAuthorised(Request $request): ?JsonResponse
    {
        $configuredSecret = trim((string) config('services.central.api_secret'));

        if ($configuredSecret === '') {
            if (app()->isLocal() || app()->runningUnitTests()) {
                return null;
            }

            return $this->error('Central API secret is not configured.', 503);
        }

        $providedSecret = trim((string) $request->header('X-Central-Secret', ''));

        if ($providedSecret === '') {
            $authorization = trim((string) $request->header('Authorization', ''));

            if (str_starts_with($authorization, 'Bearer ')) {
                $providedSecret = substr($authorization, 7);
            }
        }

        if ($providedSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            return $this->error('Unauthorized central mutation request.', 401);
        }

        return null;
    }

    /**
     * POST /api/v1/central/license/verify
     *
     * Body: { "license_key": "SK-XXXX-...", "domain": "customer.example.com" }
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string'],
            'domain'      => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->licenseService->verify(
            $validated['license_key'],
            $validated['domain'] ?? null
        );

        $httpCode = $result['status'] === 'active' ? 200 : 422;

        return response()->json($result, $httpCode);
    }

    /**
     * POST /api/v1/central/license/activate-domain
     *
     * Body: { "license_key": "SK-XXXX-...", "domain": "customer.example.com" }
     */
    public function activateDomain(Request $request): JsonResponse
    {
        if ($response = $this->ensureCentralMutationAuthorised($request)) {
            return $response;
        }

        $validated = $request->validate([
            'license_key' => ['required', 'string'],
            'domain'      => ['required', 'string', 'max:255'],
        ]);

        $license = LicenseKey::where('license_key', $validated['license_key'])->first();

        if (! $license) {
            return $this->error('License key not found.', 404);
        }

        if (! $license->status->isUsable()) {
            return $this->error('License is ' . $license->status->value . ' and cannot be modified.', 422);
        }

        $updated = $this->licenseService->activateDomain($license, $validated['domain']);

        return $this->success([
            'license_key'     => $updated->license_key,
            'allowed_domains' => $updated->allowed_domains,
        ], 'Domain activated.');
    }

    /**
     * POST /api/v1/central/license/deactivate-domain
     *
     * Body: { "license_key": "SK-XXXX-...", "domain": "customer.example.com" }
     */
    public function deactivateDomain(Request $request): JsonResponse
    {
        if ($response = $this->ensureCentralMutationAuthorised($request)) {
            return $response;
        }

        $validated = $request->validate([
            'license_key' => ['required', 'string'],
            'domain'      => ['required', 'string', 'max:255'],
        ]);

        $license = LicenseKey::where('license_key', $validated['license_key'])->first();

        if (! $license) {
            return $this->error('License key not found.', 404);
        }

        $updated = $this->licenseService->deactivateDomain($license, $validated['domain']);

        return $this->success([
            'license_key'     => $updated->license_key,
            'allowed_domains' => $updated->allowed_domains,
        ], 'Domain deactivated.');
    }
}
