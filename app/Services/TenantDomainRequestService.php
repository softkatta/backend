<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TenantDomainRequestService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly LicenseService $licenses,
    ) {}

    /**
     * @return array{
     *   status: string,
     *   frontend_domain: ?string,
     *   backend_domain: ?string,
     *   rejection_reason: ?string,
     *   submitted_at: ?string,
     *   skipped_at: ?string
     * }
     */
    public function statusForSubscription(Subscription $subscription, ?Tenant $tenant = null): array
    {
        $tenant ??= $subscription->tenant ?? ($subscription->user?->tenant_id
            ? Tenant::query()->find($subscription->user->tenant_id)
            : null);

        if (! $tenant) {
            return $this->emptyStatus('none');
        }

        if ($tenant->hasSubscriptionDomains($subscription)) {
            $pair = $tenant->subscriptionDomainPair($subscription);

            return [
                'status' => 'approved',
                'frontend_domain' => $pair['frontend_domain'] ?? null,
                'backend_domain' => $pair['backend_domain'] ?? null,
                'rejection_reason' => null,
                'submitted_at' => null,
                'skipped_at' => null,
            ];
        }

        $pending = data_get($tenant->settings, 'pending_subscription_domains.'.$subscription->id);
        if (is_array($pending) && ($pending['status'] ?? '') === 'pending') {
            return [
                'status' => 'pending',
                'frontend_domain' => isset($pending['frontend_domain']) ? (string) $pending['frontend_domain'] : null,
                'backend_domain' => isset($pending['backend_domain']) ? (string) $pending['backend_domain'] : null,
                'rejection_reason' => null,
                'submitted_at' => isset($pending['submitted_at']) ? (string) $pending['submitted_at'] : null,
                'skipped_at' => null,
            ];
        }

        if (is_array($pending) && ($pending['status'] ?? '') === 'rejected') {
            return [
                'status' => 'rejected',
                'frontend_domain' => isset($pending['frontend_domain']) ? (string) $pending['frontend_domain'] : null,
                'backend_domain' => isset($pending['backend_domain']) ? (string) $pending['backend_domain'] : null,
                'rejection_reason' => isset($pending['rejection_reason']) ? (string) $pending['rejection_reason'] : null,
                'submitted_at' => isset($pending['submitted_at']) ? (string) $pending['submitted_at'] : null,
                'skipped_at' => null,
            ];
        }

        $skip = data_get($tenant->settings, 'subscription_domain_skips.'.$subscription->id);
        if (is_array($skip) && ! empty($skip['skipped_at'])) {
            return [
                'status' => 'skipped',
                'frontend_domain' => null,
                'backend_domain' => null,
                'rejection_reason' => null,
                'submitted_at' => null,
                'skipped_at' => (string) $skip['skipped_at'],
            ];
        }

        return $this->emptyStatus('none');
    }

    /**
     * @return array{status: string, frontend_domain: null, backend_domain: null, rejection_reason: null, submitted_at: null, skipped_at: null}
     */
    protected function emptyStatus(string $status): array
    {
        return [
            'status' => $status,
            'frontend_domain' => null,
            'backend_domain' => null,
            'rejection_reason' => null,
            'submitted_at' => null,
            'skipped_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submit(User $user, Subscription $subscription, string $frontendDomain, string $backendDomain): array
    {
        $this->assertOwnsSubscription($user, $subscription);

        $frontend = $this->normalizeDomain($frontendDomain);
        $backend = $this->normalizeDomain($backendDomain);
        if ($frontend === null || $backend === null) {
            throw ValidationException::withMessages([
                'frontend_domain' => 'Enter both frontend and backend domains.',
            ]);
        }

        $tenant = $this->resolveTenantForUser($user, $subscription);
        if ($tenant->hasSubscriptionDomains($subscription)) {
            throw ValidationException::withMessages([
                'domains' => 'Domains are already approved for this subscription.',
            ]);
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $pending = is_array($settings['pending_subscription_domains'] ?? null)
            ? $settings['pending_subscription_domains']
            : [];

        $pending[(string) $subscription->id] = [
            'subscription_id' => (int) $subscription->id,
            'product_id' => (int) $subscription->product_id,
            'frontend_domain' => $frontend,
            'backend_domain' => $backend,
            'status' => 'pending',
            'submitted_at' => now()->toIso8601String(),
            'submitted_by' => $user->id,
            'rejection_reason' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ];

        $settings['pending_subscription_domains'] = $pending;

        // Clear skip if they submit after skipping.
        $skips = is_array($settings['subscription_domain_skips'] ?? null)
            ? $settings['subscription_domain_skips']
            : [];
        unset($skips[(string) $subscription->id]);
        $settings['subscription_domain_skips'] = $skips;

        $tenant->update(['settings' => $settings]);

        $this->notifyAdminsOfPending($user, $subscription, $frontend, $backend);

        return $this->statusForSubscription($subscription, $tenant->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function skip(User $user, Subscription $subscription): array
    {
        $this->assertOwnsSubscription($user, $subscription);

        $tenant = $this->resolveTenantForUser($user, $subscription);
        if ($tenant->hasSubscriptionDomains($subscription)) {
            return $this->statusForSubscription($subscription, $tenant);
        }

        $pending = data_get($tenant->settings, 'pending_subscription_domains.'.$subscription->id);
        if (is_array($pending) && ($pending['status'] ?? '') === 'pending') {
            throw ValidationException::withMessages([
                'domains' => 'A domain request is already pending admin approval. You cannot skip now.',
            ]);
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $skips = is_array($settings['subscription_domain_skips'] ?? null)
            ? $settings['subscription_domain_skips']
            : [];

        $skips[(string) $subscription->id] = [
            'skipped_at' => now()->toIso8601String(),
            'skipped_by' => $user->id,
        ];
        $settings['subscription_domain_skips'] = $skips;
        $tenant->update(['settings' => $settings]);

        return $this->statusForSubscription($subscription, $tenant->fresh());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPending(): array
    {
        $rows = [];

        Tenant::query()
            ->with('owner:id,name,email')
            ->orderByDesc('updated_at')
            ->chunkById(100, function (Collection $tenants) use (&$rows): void {
                foreach ($tenants as $tenant) {
                    $pending = data_get($tenant->settings, 'pending_subscription_domains', []);
                    if (! is_array($pending)) {
                        continue;
                    }

                    foreach ($pending as $item) {
                        if (! is_array($item) || ($item['status'] ?? '') !== 'pending') {
                            continue;
                        }

                        $subscriptionId = (int) ($item['subscription_id'] ?? 0);
                        $subscription = $subscriptionId > 0
                            ? Subscription::query()->withoutGlobalScopes()
                                ->with(['product:id,name', 'plan:id,name,billing_cycle', 'user:id,name,email'])
                                ->find($subscriptionId)
                            : null;

                        $rows[] = [
                            'tenant_id' => $tenant->id,
                            'tenant_name' => $tenant->name,
                            'owner_id' => $tenant->owner_id,
                            'owner_name' => $tenant->owner?->name,
                            'owner_email' => $tenant->owner?->email,
                            'subscription_id' => $subscriptionId,
                            'product_id' => (int) ($item['product_id'] ?? $subscription?->product_id ?? 0),
                            'product_name' => $subscription?->product?->name ?? 'Product',
                            'plan_name' => $subscription?->plan?->name
                                ?? $subscription?->plan?->billing_cycle?->value
                                ?? 'plan',
                            'frontend_domain' => (string) ($item['frontend_domain'] ?? ''),
                            'backend_domain' => (string) ($item['backend_domain'] ?? ''),
                            'submitted_at' => $item['submitted_at'] ?? null,
                            'submitted_by' => $item['submitted_by'] ?? null,
                        ];
                    }
                }
            });

        usort($rows, function (array $a, array $b): int {
            return strcmp((string) ($b['submitted_at'] ?? ''), (string) ($a['submitted_at'] ?? ''));
        });

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function approve(Tenant $tenant, int $subscriptionId, User $admin): array
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $pendingMap = is_array($settings['pending_subscription_domains'] ?? null)
            ? $settings['pending_subscription_domains']
            : [];
        $key = (string) $subscriptionId;
        $pending = $pendingMap[$key] ?? null;

        if (! is_array($pending) || ($pending['status'] ?? '') !== 'pending') {
            throw ValidationException::withMessages([
                'subscription_id' => 'No pending domain request found for this subscription.',
            ]);
        }

        $frontend = $this->normalizeDomain((string) ($pending['frontend_domain'] ?? ''));
        $backend = $this->normalizeDomain((string) ($pending['backend_domain'] ?? ''));
        if ($frontend === null || $backend === null) {
            throw ValidationException::withMessages([
                'domains' => 'Pending domain request is incomplete.',
            ]);
        }

        $subscription = Subscription::query()->withoutGlobalScopes()->findOrFail($subscriptionId);
        $approved = is_array($settings['subscription_domains'] ?? null)
            ? $settings['subscription_domains']
            : [];

        $approved[$key] = [
            'subscription_id' => $subscriptionId,
            'product_id' => (int) ($pending['product_id'] ?? $subscription->product_id),
            'frontend_domain' => $frontend,
            'backend_domain' => $backend,
        ];

        unset($pendingMap[$key]);
        $settings['subscription_domains'] = $approved;
        $settings['pending_subscription_domains'] = $pendingMap;

        $skips = is_array($settings['subscription_domain_skips'] ?? null)
            ? $settings['subscription_domain_skips']
            : [];
        unset($skips[$key]);
        $settings['subscription_domain_skips'] = $skips;

        // Clear legacy shared columns — domains are subscription-scoped.
        $tenant->update([
            'settings' => $settings,
            'frontend_domain' => null,
            'backend_domain' => null,
            'domain' => null,
            'extra_domains' => [],
        ]);

        if (! $subscription->tenant_id) {
            $subscription->update(['tenant_id' => $tenant->id]);
        }

        $this->licenses->issuePendingLicensesForTenant($tenant->fresh());

        $customer = User::query()->find($pending['submitted_by'] ?? $subscription->user_id);
        if ($customer) {
            try {
                $this->notifications->send(
                    $customer,
                    'domain_request_approved',
                    'Domains approved',
                    'Your frontend and backend domains were approved. Your license key is ready when available.',
                    [NotificationChannel::InApp, NotificationChannel::Email],
                    ['subscription_id' => $subscriptionId, 'tenant_id' => $tenant->id],
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify customer of domain approval', ['error' => $e->getMessage()]);
            }
        }

        return $this->statusForSubscription($subscription, $tenant->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(Tenant $tenant, int $subscriptionId, User $admin, ?string $reason = null): array
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $pendingMap = is_array($settings['pending_subscription_domains'] ?? null)
            ? $settings['pending_subscription_domains']
            : [];
        $key = (string) $subscriptionId;
        $pending = $pendingMap[$key] ?? null;

        if (! is_array($pending) || ($pending['status'] ?? '') !== 'pending') {
            throw ValidationException::withMessages([
                'subscription_id' => 'No pending domain request found for this subscription.',
            ]);
        }

        $pending['status'] = 'rejected';
        $pending['rejection_reason'] = $reason ? trim($reason) : 'Please update your domains and resubmit.';
        $pending['reviewed_at'] = now()->toIso8601String();
        $pending['reviewed_by'] = $admin->id;
        $pendingMap[$key] = $pending;
        $settings['pending_subscription_domains'] = $pendingMap;
        $tenant->update(['settings' => $settings]);

        $subscription = Subscription::query()->withoutGlobalScopes()->find($subscriptionId);
        $customer = User::query()->find($pending['submitted_by'] ?? $subscription?->user_id);
        if ($customer) {
            try {
                $this->notifications->send(
                    $customer,
                    'domain_request_rejected',
                    'Domains need changes',
                    'Your domain request was not approved. '.$pending['rejection_reason'],
                    [NotificationChannel::InApp, NotificationChannel::Email],
                    ['subscription_id' => $subscriptionId, 'tenant_id' => $tenant->id],
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify customer of domain rejection', ['error' => $e->getMessage()]);
            }
        }

        return $this->statusForSubscription(
            $subscription ?? new Subscription(['id' => $subscriptionId]),
            $tenant->fresh()
        );
    }

    protected function assertOwnsSubscription(User $user, Subscription $subscription): void
    {
        if ((int) $subscription->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'subscription' => 'Unauthorized.',
            ]);
        }
    }

    protected function resolveTenantForUser(User $user, Subscription $subscription): Tenant
    {
        if ($subscription->tenant_id) {
            $tenant = Tenant::query()->find($subscription->tenant_id);
            if ($tenant) {
                return $tenant;
            }
        }

        if ($user->tenant_id) {
            $tenant = Tenant::query()->find($user->tenant_id);
            if ($tenant) {
                if (! $subscription->tenant_id) {
                    $subscription->update(['tenant_id' => $tenant->id]);
                }

                return $tenant;
            }
        }

        $tenant = app(TenantService::class)->create([
            'name' => $user->company_name ?? $user->name.' Workspace',
        ], $user);

        $user->update(['tenant_id' => $tenant->id]);
        $subscription->update(['tenant_id' => $tenant->id]);

        return $tenant;
    }

    protected function normalizeDomain(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $trimmed = preg_replace('#^https?://#i', '', $trimmed) ?? $trimmed;
        $trimmed = rtrim($trimmed, '/');

        return $trimmed !== '' ? $trimmed : null;
    }

    protected function notifyAdminsOfPending(
        User $customer,
        Subscription $subscription,
        string $frontend,
        string $backend,
    ): void {
        $admins = User::query()
            ->where('is_active', true)
            ->where('role', UserRole::SuperAdmin)
            ->get();

        $productName = $subscription->product?->name ?? 'product';
        $title = 'Domain approval needed';
        $message = "{$customer->name} submitted domains for {$productName}: {$frontend} / {$backend}.";

        foreach ($admins as $admin) {
            try {
                $this->notifications->send(
                    $admin,
                    'domain_request_submitted',
                    $title,
                    $message,
                    [NotificationChannel::InApp],
                    [
                        'subscription_id' => $subscription->id,
                        'customer_id' => $customer->id,
                    ],
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify admin of domain request', [
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
