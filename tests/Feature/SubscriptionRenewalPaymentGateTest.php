<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionRenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionRenewalPaymentGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for renewal payment gate tests.');
        }

        parent::setUp();
    }

    public function test_auto_renew_creates_invoice_and_does_not_extend_without_payment(): void
    {
        [$user, $subscription] = $this->seedAutoRenewSubscription(now()->addDays(5));

        $endsBefore = $subscription->ends_at->copy();

        $created = app(SubscriptionRenewalService::class)->createPendingRenewals(7);
        $this->assertSame(1, $created);

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertSame('renewal', $invoice->billing_details['purpose'] ?? null);

        $subscription->refresh();
        $this->assertTrue($subscription->ends_at->equalTo($endsBefore));
        $this->assertNotSame(SubscriptionStatus::Expired, $subscription->status);

        // Second run is idempotent — no duplicate invoice
        $this->assertSame(0, app(SubscriptionRenewalService::class)->createPendingRenewals(7));
    }

    public function test_payment_applies_renewal_and_extends_ends_at(): void
    {
        [$user, $subscription] = $this->seedAutoRenewSubscription(now()->addDays(3));
        $renewal = app(SubscriptionRenewalService::class);
        $renewal->createRenewalInvoice($subscription);

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->latest('id')->firstOrFail();
        $endsBefore = $subscription->ends_at->copy();

        $renewal->applyPaidRenewal($subscription, $invoice);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->ends_at->greaterThan($endsBefore));
        $this->assertTrue($subscription->ends_at->equalTo($endsBefore->copy()->addMonth()));

        // Idempotent — second apply does not double-extend
        $invoice->refresh();
        $renewal->applyPaidRenewal($subscription, $invoice);
        $this->assertTrue($subscription->fresh()->ends_at->equalTo($endsBefore->copy()->addMonth()));
    }

    public function test_without_auto_renew_no_renewal_invoice_is_created(): void
    {
        [, $subscription] = $this->seedAutoRenewSubscription(now()->addDays(4), autoRenew: false);

        $this->assertSame(0, app(SubscriptionRenewalService::class)->createPendingRenewals(7));
        $this->assertSame(0, Invoice::query()->where('subscription_id', $subscription->id)->count());
    }

    /**
     * @return array{0: User, 1: Subscription}
     */
    private function seedAutoRenewSubscription(\Carbon\CarbonInterface $endsAt, bool $autoRenew = true): array
    {
        $user = User::factory()->create([
            'email' => 'client-'.Str::random(6).'@example.com',
            'role' => UserRole::Client,
            'is_active' => true,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-'.Str::random(6),
            'status' => 'active',
            'owner_id' => $user->id,
        ]);
        $user->update(['tenant_id' => $tenant->id]);

        $product = Product::query()->create([
            'name' => 'Study Point Management Software',
            'slug' => 'study-point-'.Str::random(4),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Monthly',
            'slug' => 'monthly-'.Str::random(4),
            'price' => 2999,
            'billing_cycle' => BillingCycle::Monthly,
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subMonth(),
            'ends_at' => $endsAt,
            'auto_renew' => $autoRenew,
        ]);

        return [$user, $subscription];
    }
}
