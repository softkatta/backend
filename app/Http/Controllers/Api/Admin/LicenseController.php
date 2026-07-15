<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\LicenseApiLog;
use App\Models\LicenseHistory;
use App\Models\LicenseKey;
use App\Models\Subscription;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends BaseApiController
{
    public function __construct(private readonly LicenseService $licenseService) {}

    /**
     * GET /api/v1/admin/licenses
     */
    public function index(Request $request): JsonResponse
    {
        $query = LicenseKey::with(['product', 'user', 'subscription.plan'])
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('license_key', 'like', "%{$search}%")
                  ->orWhere('allowed_domains', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        $licenses = $query->paginate(20);
        $licenses->getCollection()->transform(
            fn (LicenseKey $license): array => $this->licenseService->enrichForApi($license)
        );

        return $this->success($licenses);
    }

    /**
     * GET /api/v1/admin/licenses/{license}
     */
    public function show(LicenseKey $license): JsonResponse
    {
        return $this->success(
            $this->licenseService->enrichForApi(
                $license->load(['product', 'user', 'subscription.plan'])
            )
        );
    }

    /**
     * POST /api/v1/admin/licenses
     * Manually generate a license for an existing subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_id' => ['required', 'exists:subscriptions,id'],
            'allowed_domains' => ['nullable', 'array'],
            'allowed_domains.*' => ['string', 'max:255'],
            'max_devices'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $subscription = Subscription::findOrFail($validated['subscription_id']);

        if ($subscription->licenseKey) {
            return $this->error('A license already exists for this subscription. Use update to modify it.', 409);
        }

        $license = $this->licenseService->generateForSubscription($subscription);

        if (isset($validated['allowed_domains'])) {
            $license->update(['allowed_domains' => $validated['allowed_domains']]);
        }

        if (isset($validated['max_devices'])) {
            $license->update(['max_devices' => $validated['max_devices']]);
        }

        return $this->success(
            $this->licenseService->enrichForApi(
                $license->load(['product', 'user', 'subscription'])
            ),
            'License generated.',
            201
        );
    }

    /**
     * PUT /api/v1/admin/licenses/{license}
     */
    public function update(Request $request, LicenseKey $license): JsonResponse
    {
        $validated = $request->validate([
            'allowed_domains'   => ['nullable', 'array'],
            'allowed_domains.*' => ['string', 'max:255'],
            'max_devices'       => ['nullable', 'integer', 'min:1', 'max:100'],
            'expires_at'        => ['nullable', 'date'],
        ]);

        $license->update(array_filter($validated, fn ($v) => $v !== null));

        return $this->success(
            $this->licenseService->enrichForApi($license->fresh(['product', 'user', 'subscription'])),
            'License updated.'
        );
    }

    /**
     * POST /api/v1/admin/licenses/{license}/suspend
     */
    public function suspend(Request $request, LicenseKey $license): JsonResponse
    {
        $this->licenseService->suspend($license);

        return $this->success(null, 'License suspended.');
    }

    /**
     * POST /api/v1/admin/licenses/{license}/revoke
     */
    public function revoke(Request $request, LicenseKey $license): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->licenseService->revoke($license, $validated['reason'] ?? '');

        return $this->success(null, 'License revoked.');
    }

    /**
     * POST /api/v1/admin/licenses/{license}/activate
     */
    public function activateLicense(LicenseKey $license): JsonResponse
    {
        $this->licenseService->activate($license);

        return $this->success(null, 'License activated.');
    }

    public function resetDomains(LicenseKey $license): JsonResponse
    {
        $updated = $this->licenseService->resetDomains($license, auth()->id());

        return $this->success(
            $this->licenseService->enrichForApi($updated->load(['product', 'user', 'subscription.plan'])),
            'Domain binding reset.'
        );
    }

    public function forceLogout(LicenseKey $license): JsonResponse
    {
        $updated = $this->licenseService->forceLogout($license, auth()->id());

        return $this->success(
            $this->licenseService->enrichForApi($updated->load(['product', 'user', 'subscription.plan'])),
            'Product force logout issued.'
        );
    }

    public function activity(LicenseKey $license): JsonResponse
    {
        $logs = LicenseApiLog::query()
            ->where('license_key_id', $license->id)
            ->latest()
            ->paginate(30);

        return $this->success($logs);
    }

    public function history(LicenseKey $license): JsonResponse
    {
        $history = LicenseHistory::query()
            ->where('license_key_id', $license->id)
            ->latest()
            ->paginate(30);

        return $this->success($history);
    }

    /**
     * DELETE /api/v1/admin/licenses/{license}
     */
    public function destroy(LicenseKey $license): JsonResponse
    {
        $this->permanentlyDelete($license);

        return $this->success(null, 'License deleted.');
    }
}
