<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\NotificationChannel;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
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

            $this->notificationService->send(
                $user,
                'subscription_created',
                'Welcome to SoftKatta Solutions',
                "Your subscription to {$product->name} ({$plan->name}) has been created successfully.",
                [NotificationChannel::Email, NotificationChannel::InApp],
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
    public function purchaseForExistingUser(User $user, Product $product, Plan $plan, ?string $gateway = null): array
    {
        return DB::transaction(function () use ($user, $product, $plan, $gateway) {
            if (! $user->tenant_id) {
                $tenant = $this->tenantService->create([
                    'name' => $user->company_name ?? $user->name.' Workspace',
                ], $user);
                $user->update(['tenant_id' => $tenant->id]);
            }

            $user->refresh();

            $amount = (float) $plan->price;
            $requiresPayment = ! $product->has_free_trial && $amount > 0;
            $gatewayName = $gateway ?? 'razorpay';

            $subscription = $this->createSubscription($user, $product, $plan, $requiresPayment);
            $order = $this->createOrder($user, $product, $plan, $requiresPayment, $gatewayName);
            $invoice = $this->invoiceService->generateFromOrder($order, $subscription);

            if ($requiresPayment) {
                $invoice->update(['status' => InvoiceStatus::Sent]);
            } else {
                $this->invoiceService->markAsPaid($invoice);
            }

            $this->notificationService->send(
                $user,
                'subscription_created',
                $requiresPayment ? 'Subscription created' : 'Subscription activated',
                $requiresPayment
                    ? "Your subscription to {$product->name} ({$plan->name}) is pending payment."
                    : "Your subscription to {$product->name} ({$plan->name}) is now active.",
                [NotificationChannel::Email, NotificationChannel::InApp],
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

            $order->update([
                'status' => 'completed',
                'payment_id' => $payload['razorpay_payment_id'] ?? $payment->transaction_id,
            ]);

            if ($order->invoice) {
                $this->invoiceService->markAsPaid($order->invoice);
            }

            $subscription = Subscription::query()
                ->where('user_id', $order->user_id)
                ->where('product_id', $order->product_id)
                ->where('plan_id', $order->plan_id)
                ->latest('id')
                ->first();

            if ($subscription) {
                $subscription->update(['status' => SubscriptionStatus::Active]);
            }

            return [
                'payment' => $payment->fresh(),
                'order' => $order->fresh(['invoice']),
                'invoice' => $order->invoice?->fresh(),
                'subscription' => $subscription?->fresh(),
                'already_completed' => false,
            ];
        });
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

    protected function createOrder(User $user, Product $product, Plan $plan, bool $requiresPayment, string $gateway): Order
    {
        $amount = (float) $plan->price;
        $taxRate = $this->invoiceProfile->gstRate();
        $taxAmount = round($amount * ($taxRate / 100), 2);
        $totalAmount = $amount + $taxAmount;

        return Order::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'order_number' => 'SK-ORD-'.strtoupper(Str::random(10)),
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'status' => $requiresPayment ? 'pending' : 'completed',
            'payment_gateway' => $gateway,
        ]);
    }
}
