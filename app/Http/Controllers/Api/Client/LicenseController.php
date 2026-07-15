<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\LicenseApiLog;
use App\Models\LicenseDomainResetRequest;
use App\Models\LicenseHistory;
use App\Models\LicenseKey;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends BaseApiController
{
    public function __construct(private readonly LicenseService $licenseService) {}

    public function index(Request $request): JsonResponse
    {
        $licenses = LicenseKey::with(['product', 'subscription.plan'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        $licenses->getCollection()->transform(
            fn (LicenseKey $license): array => $this->licenseService->enrichForApi($license)
        );

        return $this->success($licenses);
    }

    public function show(Request $request, LicenseKey $license): JsonResponse
    {
        if ($response = $this->authorizeLicense($request, $license)) {
            return $response;
        }

        return $this->success(
            $this->licenseService->enrichForApi(
                $license->load(['product', 'subscription.plan'])
            )
        );
    }

    public function registerDomain(Request $request, LicenseKey $license): JsonResponse
    {
        if ($response = $this->authorizeLicense($request, $license)) {
            return $response;
        }

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        try {
            $updated = $this->licenseService->registerDomain(
                $license,
                $validated['domain'],
                $request->ip(),
                $request->user()->id
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(
            $this->licenseService->enrichForApi($updated->load(['product', 'subscription.plan'])),
            'Domain registered successfully.'
        );
    }

    public function removeDomain(Request $request, LicenseKey $license): JsonResponse
    {
        if ($response = $this->authorizeLicense($request, $license)) {
            return $response;
        }

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $updated = $this->licenseService->deactivateDomain($license, $validated['domain']);

        return $this->success(
            $this->licenseService->enrichForApi($updated->load(['product', 'subscription.plan'])),
            'Domain removed successfully.'
        );
    }

    public function requestDomainReset(Request $request, LicenseKey $license): JsonResponse
    {
        if ($response = $this->authorizeLicense($request, $license)) {
            return $response;
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $resetRequest = LicenseDomainResetRequest::create([
            'license_key_id' => $license->id,
            'user_id' => $request->user()->id,
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        return $this->success($resetRequest, 'Domain reset request submitted.', 201);
    }

    public function activateProduct(Request $request, LicenseKey $license): JsonResponse
    {
        if ($response = $this->authorizeLicense($request, $license)) {
            return $response;
        }

        $updated = $this->licenseService->activateProduct($license, $request->user()->id);

        return $this->success(
            $this->licenseService->enrichForApi($updated->load(['product', 'subscription.plan'])),
            'Product activated.'
        );
    }

    public function deactivateProduct(Request $request, LicenseKey $license): JsonResponse
    {
        if ($response = $this->authorizeLicense($request, $license)) {
            return $response;
        }

        $updated = $this->licenseService->deactivateProduct($license, $request->user()->id);

        return $this->success(
            $this->licenseService->enrichForApi($updated->load(['product', 'subscription.plan'])),
            'Product deactivated.'
        );
    }

    public function activity(Request $request, LicenseKey $license): JsonResponse
    {
        if ($response = $this->authorizeLicense($request, $license)) {
            return $response;
        }

        $logs = LicenseApiLog::query()
            ->where('license_key_id', $license->id)
            ->latest()
            ->paginate(20);

        return $this->success($logs);
    }

    public function history(Request $request, LicenseKey $license): JsonResponse
    {
        if ($response = $this->authorizeLicense($request, $license)) {
            return $response;
        }

        $history = LicenseHistory::query()
            ->where('license_key_id', $license->id)
            ->latest()
            ->paginate(20);

        return $this->success($history);
    }

    private function authorizeLicense(Request $request, LicenseKey $license): ?JsonResponse
    {
        if ($license->user_id !== $request->user()->id) {
            return $this->error('Not found.', 404);
        }

        return null;
    }
}
