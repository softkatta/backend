<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Subscription;
use App\Services\SubscriptionRenewalService;
use App\Services\TenantDomainRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseApiController
{
    public function __construct(
        private readonly SubscriptionRenewalService $renewals,
        private readonly TenantDomainRequestService $domainRequests,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptions = Subscription::with(['product', 'plan', 'tenant', 'user'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        $subscriptions->getCollection()->transform(function (Subscription $subscription) {
            $subscription->setAttribute(
                'domain_setup',
                $this->domainRequests->statusForSubscription($subscription)
            );

            return $subscription;
        });

        return $this->success($subscriptions);
    }

    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        $subscription->load(['product', 'plan', 'invoices', 'tenant', 'user']);
        $subscription->setAttribute(
            'domain_setup',
            $this->domainRequests->statusForSubscription($subscription)
        );

        return $this->success($subscription);
    }

    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        $subscription->update([
            'auto_renew' => false,
            'cancelled_at' => now(),
            'status' => SubscriptionStatus::ExpiringSoon,
        ]);

        $this->renewals->cancelOpenRenewalInvoices($subscription);

        return $this->success($subscription->fresh(), 'Subscription cancellation scheduled. Access continues until expiry; no renewal charge will be created.');
    }

    /**
     * Let a customer request renewal from their subscriptions or product page.
     * Payment of the generated invoice is what actually extends access.
     */
    public function renew(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        $subscription->load(['user', 'product', 'plan']);

        if (! $subscription->plan?->billing_cycle?->months()) {
            return $this->error('This subscription plan cannot be renewed.', 422);
        }

        if ($this->renewals->hasOpenRenewalInvoice($subscription)) {
            return $this->error('A renewal invoice is already awaiting payment for this subscription.', 422);
        }

        $result = $this->renewals->createRenewalInvoice($subscription);

        return $this->success(
            [
                'subscription' => $subscription->fresh(['product', 'plan', 'tenant', 'user']),
                'order' => $result['order'],
                'invoice' => $result['invoice'],
            ],
            'Renewal invoice created. Complete payment from Invoices to extend your subscription.',
        );
    }

    public function domainStatus(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success(
            $this->domainRequests->statusForSubscription($subscription->load(['tenant', 'user', 'product']))
        );
    }

    public function submitDomains(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        $data = $request->validate([
            'frontend_domain' => ['required', 'string', 'max:255'],
            'backend_domain' => ['required', 'string', 'max:255'],
        ]);

        $status = $this->domainRequests->submit(
            $request->user(),
            $subscription->load(['product', 'tenant', 'user']),
            $data['frontend_domain'],
            $data['backend_domain'],
        );

        return $this->success($status, 'Domains submitted for admin approval.');
    }

    public function skipDomains(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        $status = $this->domainRequests->skip(
            $request->user(),
            $subscription->load(['product', 'tenant', 'user']),
        );

        return $this->success($status, 'Domain setup skipped. You can add domains anytime from Subscriptions.');
    }
}
