<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\NotificationChannel;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        protected TenantService $tenantService,
        protected InvoiceService $invoiceService,
        protected NotificationService $notificationService,
        protected PaymentService $paymentService,
        protected InvoiceProfileService $invoiceProfile,
        protected LicenseService $licenseService,
        protected CouponService $couponService,
        protected SubscriptionRenewalService $renewalService,
        protected ExtraSeatsPurchaseService $extraSeatsPurchaseService,
    ) {}

    /**
     * Full purchase flow: account, tenant, subscription, invoice, notifications.
     *
     * @param  array<string, mixed>  $userData
     * @return array{user: User, tenant: \App\Models\Tenant, subscription: Subscription, order: Order, invoice: Invoice}
     */
    public function purchase(Product $product, Plan $plan, array $userData, ?string $gateway = null): array
    {
        return DB::transaction(function () use ($product, $plan, $userData, $gateway) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'phone' => $userData['phone'] ?? null,
                'role' => UserRole::Client,
                'two_factor_email_enabled' => true,
                'company_name' => $userData['company_name'] ?? null,
                'gst_number' => $userData['gst_number'] ?? null,
                'address' => $userData['address'] ?? null,
                'city' => $userData['city'] ?? null,
                'state' => $userData['state'] ?? null,
                'pincode' => $userData['pincode'] ?? null,
                'country' => $userData['country'] ?? 'India',
                'is_active' => true,
            ]);

            $user->assignRole('client');

            $tenant = $this->tenantService->create([
                'name' => $userData['company_name'] ?? $userData['name'].' Workspace',
            ], $user);

            $user->update(['tenant_id' => $tenant->id]);

            $taxRate = $this->invoiceProfile->gstRate();
            $amount = (float) $plan->price;
            $taxAmount = round($amount * ($taxRate / 100), 2);
            $totalAmount = $amount + $taxAmount;

            $startsAt = now();
            $trialEndsAt = $product->has_free_trial
                ? $startsAt->copy()->addDays($product->trial_days)
                : null;

            $endsAt = match ($plan->billing_cycle->value) {
                'yearly' => $startsAt->copy()->addYear(),
                'monthly' => $startsAt->copy()->addMonth(),
                default => $startsAt->copy()->addMonth(),
            };

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'product_id' => $product->id,
                'plan_id' => $plan->id,
                'status' => $product->has_free_trial ? SubscriptionStatus::Active : SubscriptionStatus::Pending,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'trial_ends_at' => $trialEndsAt,
                'auto_renew' => true,
            ]);

            $order = Order::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'product_id' => $product->id,
                'plan_id' => $plan->id,
                'order_number' => 'SK-ORD-'.strtoupper(Str::random(10)),
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => $product->has_free_trial ? 'completed' : 'pending',
                'payment_gateway' => $gateway,
            ]);

            $invoice = $this->invoiceService->generateFromOrder($order, $subscription);

            if ($product->has_free_trial) {
                $this->invoiceService->markAsPaid($invoice);
            }

            $this->issueLicenseIfEligible($subscription);

            $this->notificationService->send(
                $user,
                'subscription_created',
                'Welcome to SoftKatta Solutions',
                "Your subscription to {$product->name} ({$plan->name}) has been created successfully.",
                [NotificationChannel::Email, NotificationChannel::Whatsapp, NotificationChannel::InApp],
                ['product_id' => $product->id, 'plan_id' => $plan->id]
            );

            return compact('user', 'tenant', 'subscription', 'order', 'invoice');
        });
    }

    /**
     * Purchase for an authenticated client (no new user registration).
     *
     * @return array<string, mixed>
     */
    public function purchaseForExistingUser(User $user, Product $product, Plan $plan, ?string $gateway = null, ?string $couponCode = null): array
    {
        return DB::transaction(function () use ($user, $product, $plan, $gateway, $couponCode) {
            if (! $user->tenant_id) {
                $tenant = $this->tenantService->create([
                    'name' => $user->company_name ?? $user->name.' Workspace',
                ], $user);
                $user->update(['tenant_id' => $tenant->id]);
            }

            $user->refresh();

            $amount = (float) $plan->price;
            $gatewayName = $gateway ?? 'razorpay';

            $couponContext = $this->resolveCouponContext($user, $couponCode, [[
                'product' => $product,
                'plan' => $plan,
                'amount' => $amount,
            ]]);
            $lineDiscount = (float) ($couponContext['line_discounts'][0] ?? 0);
            $netAmount = max(0, $amount - $lineDiscount);
            $requiresPayment = ! $product->has_free_trial && $netAmount > 0;

            $subscription = $this->createSubscription($user, $product, $plan, $requiresPayment);
            $order = $this->createOrder(
                $user,
                $product,
                $plan,
                $requiresPayment,
                $gatewayName,
                $lineDiscount,
                $couponContext['coupon'],
            );
            $invoice = $this->invoiceService->generateFromOrder($order, $subscription);

            if ($couponContext['coupon'] && $lineDiscount > 0) {
                $this->couponService->recordRedemption(
                    $couponContext['coupon'],
                    $user,
                    $order,
                    $lineDiscount,
                );
            }

            if ($requiresPayment) {
                $invoice->update(['status' => InvoiceStatus::Sent]);
            } else {
                $this->invoiceService->markAsPaid($invoice);
                $this->issueLicenseIfEligible($subscription);
            }

            $this->notificationService->send(
                $user,
                'subscription_created',
                $requiresPayment ? 'Subscription created' : 'Subscription activated',
                $requiresPayment
                    ? "Your subscription to {$product->name} ({$plan->name}) is pending payment."
                    : "Your subscription to {$product->name} ({$plan->name}) is now active.",
                [NotificationChannel::Email, NotificationChannel::Whatsapp, NotificationChannel::InApp],
                ['product_id' => $product->id, 'plan_id' => $plan->id]
            );

            $result = [
                'requires_payment' => $requiresPayment,
                'skip_payment_reason' => $requiresPayment ? null : ($product->has_free_trial ? 'free_trial' : 'no_payment_required'),
                'subscription' => $subscription->fresh(),
                'order' => $order->fresh(),
                'invoice' => $invoice->fresh(),
            ];

            if ($requiresPayment) {
                $checkout = $this->paymentService->initiate($order->fresh(['invoice']), $gatewayName);
                $result['payment'] = $checkout['payment'];
                $result['checkout'] = $checkout['checkout'];
            }

            return $result;
        });
    }

    /**
     * Purchase multiple products for an authenticated client in one checkout.
     *
     * @param  list<array{product: Product, plan: Plan}>  $lineItems
     * @return array<string, mixed>
     */
    public function purchaseBatchForExistingUser(User $user, array $lineItems, ?string $gateway = null, ?string $couponCode = null): array
    {
        return DB::transaction(function () use ($user, $lineItems, $gateway, $couponCode) {
            if (! $user->tenant_id) {
                $tenant = $this->tenantService->create([
                    'name' => $user->company_name ?? $user->name.' Workspace',
                ], $user);
                $user->update(['tenant_id' => $tenant->id]);
            }

            $user->refresh();

            $gatewayName = $gateway ?? 'razorpay';
            $entries = [];
            $paidOrders = [];

            $pricedLineItems = collect($lineItems)->map(function (array $lineItem): array {
                $product = $lineItem['product'];
                $plan = $lineItem['plan'];

                return [
                    'product' => $product,
                    'plan' => $plan,
                    'amount' => (float) $plan->price,
                ];
            })->values()->all();

            $couponContext = $this->resolveCouponContext($user, $couponCode, $pricedLineItems);

            foreach ($pricedLineItems as $index => $lineItem) {
                $product = $lineItem['product'];
                $plan = $lineItem['plan'];
                $amount = (float) $lineItem['amount'];
                $lineDiscount = (float) ($couponContext['line_discounts'][$index] ?? 0);
                $netAmount = max(0, $amount - $lineDiscount);
                $requiresPayment = ! $product->has_free_trial && $netAmount > 0;

                $subscription = $this->createSubscription($user, $product, $plan, $requiresPayment);
                $order = $this->createOrder(
                    $user,
                    $product,
                    $plan,
                    $requiresPayment,
                    $gatewayName,
                    $lineDiscount,
                    $couponContext['coupon'],
                );
                $invoice = $this->invoiceService->generateFromOrder($order, $subscription);

                if ($requiresPayment) {
                    $invoice->update(['status' => InvoiceStatus::Sent]);
                    $paidOrders[] = $order;
                } else {
                    $this->invoiceService->markAsPaid($invoice);
                    $this->issueLicenseIfEligible($subscription);
                }

                $this->notificationService->send(
                    $user,
                    'subscription_created',
                    $requiresPayment ? 'Subscription created' : 'Subscription activated',
                    $requiresPayment
                        ? "Your subscription to {$product->name} ({$plan->name}) is pending payment."
                        : "Your subscription to {$product->name} ({$plan->name}) is now active.",
                    [NotificationChannel::Email, NotificationChannel::Whatsapp, NotificationChannel::InApp],
                    ['product_id' => $product->id, 'plan_id' => $plan->id]
                );

                $entries[] = [
                    'requires_payment' => $requiresPayment,
                    'subscription' => $subscription->fresh(),
                    'order' => $order->fresh(),
                    'invoice' => $invoice->fresh(),
                    'product' => $product,
                ];
            }

            if ($couponContext['coupon'] && ($couponContext['discount_amount'] ?? 0) > 0) {
                $this->couponService->recordRedemption(
                    $couponContext['coupon'],
                    $user,
                    $entries[0]['order'],
                    (float) $couponContext['discount_amount'],
                );
            }

            $orders = collect($entries)->pluck('order')->values()->all();
            $invoices = collect($entries)->pluck('invoice')->values()->all();
            $subscriptions = collect($entries)->pluck('subscription')->values()->all();
            $primaryOrder = $entries[0]['order'];

            $result = [
                'requires_payment' => $paidOrders !== [],
                'skip_payment_reason' => $paidOrders === [] ? 'no_payment_required' : null,
                'orders' => $orders,
                'invoices' => $invoices,
                'subscriptions' => $subscriptions,
                'order' => $primaryOrder,
                'invoice' => $entries[0]['invoice'],
                'subscription' => $entries[0]['subscription'],
                'item_count' => count($entries),
            ];

            if ($paidOrders === []) {
                return $result;
            }

            $combinedTotal = collect($paidOrders)->sum(fn (Order $order) => (float) $order->total_amount);
            $relatedOrderIds = collect($paidOrders)->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
            $primaryPaidOrder = $paidOrders[0];

            $checkout = $this->paymentService->initiate($primaryPaidOrder->fresh(['invoice']), $gatewayName, [
                'amount' => $combinedTotal,
                'related_order_ids' => $relatedOrderIds,
            ]);

            $result['payment'] = $checkout['payment'];
            $result['checkout'] = $checkout['checkout'];

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function completePayment(Payment $payment, array $payload): array
    {
        if ($payment->status === PaymentStatus::Completed) {
            $payment->load(['order.invoice']);

            return [
                'payment' => $payment,
                'order' => $payment->order,
                'invoice' => $payment->order?->invoice,
                'already_completed' => true,
            ];
        }

        $verified = $this->paymentService->verify($payment, $payload);

        if (! $verified) {
            throw ValidationException::withMessages([
                'payment' => ['Payment verification failed. Please try again or contact support.'],
            ]);
        }

        return DB::transaction(function () use ($payment, $payload) {
            $payment->refresh();
            $order = $payment->order()->with('invoice')->firstOrFail();
            $relatedOrderIds = $this->relatedOrderIdsFromPayment($payment);

            $ordersToComplete = Order::query()
                ->where('user_id', $order->user_id)
                ->whereIn('id', $relatedOrderIds)
                ->with('invoice')
                ->get();

            foreach ($ordersToComplete as $relatedOrder) {
                $relatedOrder->update([
                    'status' => 'completed',
                    'payment_id' => $payload['razorpay_payment_id'] ?? $payment->transaction_id,
                ]);

                if ($relatedOrder->invoice) {
                    $this->invoiceService->markAsPaid($relatedOrder->invoice);
                    $relatedOrder->setRelation('invoice', $relatedOrder->invoice->fresh());
                }

                $this->fulfillPaidOrder($relatedOrder);
            }

            return [
                'payment' => $payment->fresh(),
                'order' => $order->fresh(['invoice']),
                'invoice' => $order->invoice?->fresh(),
                'subscription' => $this->resolveSubscriptionForOrder($order)?->fresh(),
                'orders' => $ordersToComplete->map->fresh(['invoice'])->values()->all(),
                'already_completed' => false,
            ];
        });
    }

    /**
     * Activate first purchase or extend renewal — never extends without a paid invoice path.
     */
    public function fulfillPaidOrder(Order $order): ?Subscription
    {
        $order->loadMissing(['invoice', 'product', 'plan']);
        $invoice = $order->invoice;
        $subscription = $this->resolveSubscriptionForOrder($order);

        if (! $subscription) {
            return null;
        }

        if ($this->renewalService->isRenewalInvoice($invoice)) {
            return $this->renewalService->applyPaidRenewal($subscription, $invoice);
        }

        if ($this->extraSeatsPurchaseService->isExtraSeatsInvoice($invoice)) {
            $licenseId = (int) (($invoice->billing_details ?? [])['license_id'] ?? 0);
            $license = $licenseId > 0
                ? \App\Models\LicenseKey::query()->find($licenseId)
                : $subscription->licenseKey;
            if ($license) {
                $this->extraSeatsPurchaseService->applyPaidExtraSeats($license, $invoice);
            }

            return $subscription->fresh();
        }

        $subscription->update(['status' => SubscriptionStatus::Active]);
        $this->issueLicenseIfEligible($subscription);

        return $subscription->fresh();
    }

    protected function resolveSubscriptionForOrder(Order $order): ?Subscription
    {
        $order->loadMissing('invoice');

        if ($order->invoice?->subscription_id) {
            return Subscription::query()->find($order->invoice->subscription_id);
        }

        return Subscription::query()
            ->where('user_id', $order->user_id)
            ->where('product_id', $order->product_id)
            ->where('plan_id', $order->plan_id)
            ->latest('id')
            ->first();
    }

    /**
     * @return list<int|string>
     */
    protected function relatedOrderIdsFromPayment(Payment $payment): array
    {
        $gatewayResponse = is_array($payment->gateway_response) ? $payment->gateway_response : [];
        $related = $gatewayResponse['related_order_ids'] ?? [];

        if (! is_array($related) || $related === []) {
            return [$payment->order_id];
        }

        return array_values(array_unique(array_merge([$payment->order_id], $related)));
    }

    protected function issueLicenseIfEligible(Subscription $subscription): void
    {
        $subscription->refresh();

        if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Trial], true)) {
            return;
        }

        try {
            $this->licenseService->generateForSubscription($subscription);
        } catch (\App\Exceptions\TenantDomainsRequiredException $e) {
            \Illuminate\Support\Facades\Log::info('License deferred until SoftKatta Admin assigns tenant domains', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function createSubscription(User $user, Product $product, Plan $plan, bool $requiresPayment): Subscription
    {
        $startsAt = now();
        $trialEndsAt = $product->has_free_trial
            ? $startsAt->copy()->addDays($product->trial_days)
            : null;

        $endsAt = match ($plan->billing_cycle->value) {
            'yearly' => $startsAt->copy()->addYear(),
            'monthly' => $startsAt->copy()->addMonth(),
            default => $startsAt->copy()->addMonth(),
        };

        return Subscription::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => $requiresPayment ? SubscriptionStatus::Pending : SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $trialEndsAt,
            'auto_renew' => true,
        ]);
    }

    protected function createOrder(
        User $user,
        Product $product,
        Plan $plan,
        bool $requiresPayment,
        string $gateway,
        float $discountAmount = 0,
        ?Coupon $coupon = null,
    ): Order {
        $grossAmount = (float) $plan->price;
        $discountAmount = max(0, min($discountAmount, $grossAmount));
        $amount = round($grossAmount - $discountAmount, 2);
        $taxRate = $this->invoiceProfile->gstRate();
        $taxAmount = round($amount * ($taxRate / 100), 2);
        $totalAmount = $amount + $taxAmount;

        return Order::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'order_number' => 'SK-ORD-'.strtoupper(Str::random(10)),
            'amount' => $amount,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'status' => $requiresPayment ? 'pending' : 'completed',
            'payment_gateway' => $gateway,
        ]);
    }

    /**
     * @param  list<array{product: Product, plan: Plan, amount: float}>  $lineItems
     * @return array{coupon: ?Coupon, discount_amount: float, line_discounts: list<float>}
     */
    protected function resolveCouponContext(User $user, ?string $couponCode, array $lineItems): array
    {
        if (! $couponCode) {
            return [
                'coupon' => null,
                'discount_amount' => 0,
                'line_discounts' => array_fill(0, count($lineItems), 0.0),
            ];
        }

        $result = $this->couponService->validateForCheckout($user, $couponCode, $lineItems);

        return [
            'coupon' => $result['coupon'],
            'discount_amount' => $result['discount_amount'],
            'line_discounts' => $result['line_discounts'],
        ];
    }
}
