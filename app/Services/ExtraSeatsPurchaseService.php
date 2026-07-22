<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LicenseStatus;
use App\Models\Invoice;
use App\Models\LicenseKey;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExtraSeatsPurchaseService
{
    public const PURPOSE = 'extra_seats';

    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceProfileService $invoiceProfile,
        private readonly PaymentService $paymentService,
        private readonly LicenseService $licenseService,
    ) {}

    /**
     * @return array{
     *     order: Order,
     *     invoice: Invoice,
     *     payment: mixed,
     *     checkout: array<string, mixed>,
     *     quote: array<string, mixed>
     * }
     */
    public function purchase(User $user, LicenseKey $license, int $extraUsers, int $extraStudents, string $gateway = 'razorpay'): array
    {
        $extraUsers = max(0, $extraUsers);
        $extraStudents = max(0, $extraStudents);

        if ($extraUsers === 0 && $extraStudents === 0) {
            throw new InvalidArgumentException('Select at least one extra user or student seat.');
        }

        $license->loadMissing(['product', 'subscription.plan', 'user']);

        if ((int) $license->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('License does not belong to this account.');
        }

        if ($license->status !== LicenseStatus::Active || ! $license->is_product_active) {
            throw new InvalidArgumentException('Only active licenses can purchase extra seats.');
        }

        $subscription = $license->subscription;
        $product = $license->product;
        $plan = $subscription?->plan;

        if (! $subscription || ! $product || ! $plan) {
            throw new InvalidArgumentException('License subscription or product is missing.');
        }

        $quote = $this->quote($product, $extraUsers, $extraStudents);
        if ($extraUsers > 0 && $quote['price_per_extra_user'] <= 0) {
            throw new InvalidArgumentException('Extra user pricing is not configured for this product. Ask SoftKatta Admin to set a price per extra user.');
        }
        if ($extraStudents > 0 && $quote['price_per_extra_student'] <= 0) {
            throw new InvalidArgumentException('Extra student pricing is not configured for this product. Ask SoftKatta Admin to set a price per extra student.');
        }
        if ($quote['subtotal'] <= 0) {
            throw new InvalidArgumentException('Extra seat pricing is not configured for this product. Ask SoftKatta Admin to set prices.');
        }

        return DB::transaction(function () use ($user, $license, $subscription, $product, $plan, $extraUsers, $extraStudents, $quote, $gateway) {
            $order = Order::create([
                'tenant_id' => $subscription->tenant_id,
                'user_id' => $user->id,
                'product_id' => $product->id,
                'plan_id' => $plan->id,
                'order_number' => 'SK-SEAT-'.strtoupper(Str::random(10)),
                'amount' => $quote['subtotal'],
                'discount_amount' => 0,
                'tax_amount' => $quote['tax_amount'],
                'total_amount' => $quote['total'],
                'status' => 'pending',
                'payment_gateway' => $gateway,
            ]);

            $parts = [];
            if ($extraUsers > 0) {
                $parts[] = "{$extraUsers} extra user".($extraUsers === 1 ? '' : 's');
            }
            if ($extraStudents > 0) {
                $parts[] = "{$extraStudents} extra student".($extraStudents === 1 ? '' : 's');
            }

            $invoice = $this->invoiceService->generateFromOrder($order, $subscription, [
                'item_description' => 'Extra seats — '.$product->name.' — '.implode(' + ', $parts),
                'billing_details' => [
                    'purpose' => self::PURPOSE,
                    'license_id' => $license->id,
                    'extra_users' => $extraUsers,
                    'extra_students' => $extraStudents,
                    'unit_price_user' => $quote['price_per_extra_user'],
                    'unit_price_student' => $quote['price_per_extra_student'],
                ],
                'due_date' => now()->addDays(7)->toDateString(),
            ]);

            $invoice->update(['status' => InvoiceStatus::Sent]);

            $checkoutBundle = $this->paymentService->initiate($order->fresh(['invoice']), $gateway);

            return [
                'order' => $order->fresh(),
                'invoice' => $invoice->fresh(),
                'payment' => $checkoutBundle['payment'],
                'checkout' => $checkoutBundle['checkout'],
                'quote' => $quote,
                'requires_payment' => true,
            ];
        });
    }

    public function isExtraSeatsInvoice(?Invoice $invoice): bool
    {
        if (! $invoice) {
            return false;
        }

        $details = $invoice->billing_details ?? [];

        return ($details['purpose'] ?? null) === self::PURPOSE;
    }

    public function applyPaidExtraSeats(LicenseKey $license, Invoice $invoice): LicenseKey
    {
        return DB::transaction(function () use ($license, $invoice) {
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $details = $lockedInvoice->billing_details ?? [];
            if (! empty($details['extra_seats_applied_at'])) {
                return LicenseKey::query()->with(['product', 'subscription.plan', 'user'])->findOrFail($license->id);
            }

            $extraUsers = max(0, (int) ($details['extra_users'] ?? 0));
            $extraStudents = max(0, (int) ($details['extra_students'] ?? 0));

            $lockedLicense = LicenseKey::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();
            $meta = is_array($lockedLicense->meta) ? $lockedLicense->meta : [];
            $meta['extra_max_users'] = max(0, (int) ($meta['extra_max_users'] ?? 0)) + $extraUsers;
            $meta['extra_max_students'] = max(0, (int) ($meta['extra_max_students'] ?? 0)) + $extraStudents;

            $lockedLicense->update(['meta' => $meta]);

            $lockedInvoice->update([
                'billing_details' => array_merge($details, [
                    'extra_seats_applied_at' => now()->toIso8601String(),
                ]),
            ]);

            $this->licenseService->recordHistory(
                $lockedLicense->fresh(),
                'extra_seats_purchased',
                [
                    'extra_users' => $extraUsers,
                    'extra_students' => $extraStudents,
                    'invoice_id' => $lockedInvoice->id,
                ],
                $lockedLicense->user_id,
            );

            return $lockedLicense->fresh(['product', 'subscription.plan', 'user']);
        });
    }

    /**
     * @return array{
     *     price_per_extra_user: float,
     *     price_per_extra_student: float,
     *     extra_users: int,
     *     extra_students: int,
     *     users_subtotal: float,
     *     students_subtotal: float,
     *     subtotal: float,
     *     tax_rate: float,
     *     tax_amount: float,
     *     total: float
     * }
     */
    public function quote(\App\Models\Product $product, int $extraUsers, int $extraStudents): array
    {
        $meta = is_array($product->meta) ? $product->meta : [];
        $priceUser = max(0, (float) ($meta['price_per_extra_user'] ?? 0));
        $priceStudent = max(0, (float) ($meta['price_per_extra_student'] ?? 0));

        $usersSubtotal = round($extraUsers * $priceUser, 2);
        $studentsSubtotal = round($extraStudents * $priceStudent, 2);
        $subtotal = round($usersSubtotal + $studentsSubtotal, 2);
        $taxRate = $this->invoiceProfile->gstRate();
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        return [
            'price_per_extra_user' => $priceUser,
            'price_per_extra_student' => $priceStudent,
            'extra_users' => $extraUsers,
            'extra_students' => $extraStudents,
            'users_subtotal' => $usersSubtotal,
            'students_subtotal' => $studentsSubtotal,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => round($subtotal + $taxAmount, 2),
        ];
    }
}
