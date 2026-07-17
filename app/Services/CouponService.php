<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * @param  list<array{product: Product, plan: Plan, amount: float}>  $lineItems
     * @return array{
     *     coupon: Coupon,
     *     subtotal: float,
     *     discount_amount: float,
     *     line_discounts: list<float>
     * }
     */
    public function validateForCheckout(User $user, string $code, array $lineItems): array
    {
        $coupon = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [Str::upper(trim($code))])
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Invalid coupon code.'],
            ]);
        }

        $this->assertCouponUsable($coupon, $user);

        $eligibleSubtotal = 0.0;
        $eligibleIndexes = [];

        foreach ($lineItems as $index => $line) {
            if ($this->lineEligible($coupon, $line['product'])) {
                $eligibleSubtotal += (float) $line['amount'];
                $eligibleIndexes[] = $index;
            }
        }

        if ($coupon->product_id && $eligibleSubtotal <= 0) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon does not apply to the selected products.'],
            ]);
        }

        $subtotal = collect($lineItems)->sum(fn (array $line) => (float) $line['amount']);

        if ($coupon->min_order_amount && $eligibleSubtotal < (float) $coupon->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Minimum order amount not met for this coupon.'],
            ]);
        }

        $discountBase = $coupon->product_id ? $eligibleSubtotal : $subtotal;
        $discountAmount = $this->calculateDiscount($coupon, $discountBase);

        if ($discountAmount <= 0) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon cannot be applied to this order.'],
            ]);
        }

        $lineDiscounts = array_fill(0, count($lineItems), 0.0);

        if ($coupon->product_id) {
            $this->distributeDiscount($lineItems, $eligibleIndexes, $discountAmount, $lineDiscounts);
        } else {
            $this->distributeDiscount($lineItems, array_keys($lineItems), $discountAmount, $lineDiscounts);
        }

        return [
            'coupon' => $coupon,
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'line_discounts' => array_map(fn (float $value) => round($value, 2), $lineDiscounts),
        ];
    }

    public function recordRedemption(Coupon $coupon, User $user, Order $order, float $discountAmount): void
    {
        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
        ]);

        $coupon->increment('used_count');
    }

    protected function assertCouponUsable(Coupon $coupon, User $user): void
    {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon is not active.'],
            ]);
        }

        $now = now();

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon is not valid yet.'],
            ]);
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon has expired.'],
            ]);
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon has reached its usage limit.'],
            ]);
        }

        $userUses = CouponRedemption::query()
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->count();

        $perUserLimit = $coupon->max_uses_per_user ?? 1;

        if ($userUses >= $perUserLimit) {
            throw ValidationException::withMessages([
                'coupon_code' => ['You have already used this coupon.'],
            ]);
        }
    }

    /**
     * @param  array{product: Product, plan: Plan}  $line
     */
    protected function lineEligible(Coupon $coupon, Product $product): bool
    {
        if (! $coupon->product_id) {
            return true;
        }

        return (int) $coupon->product_id === (int) $product->id;
    }

    protected function calculateDiscount(Coupon $coupon, float $baseAmount): float
    {
        if ($baseAmount <= 0) {
            return 0;
        }

        $discount = match ($coupon->type) {
            'fixed' => (float) $coupon->value,
            default => round($baseAmount * ((float) $coupon->value / 100), 2),
        };

        return min($discount, $baseAmount);
    }

    /**
     * @param  list<array{amount: float}>  $lineItems
     * @param  list<int>  $indexes
     * @param  list<float>  $lineDiscounts
     */
    protected function distributeDiscount(array $lineItems, array $indexes, float $discountAmount, array &$lineDiscounts): void
    {
        $eligibleTotal = collect($indexes)->sum(fn (int $index) => (float) $lineItems[$index]['amount']);

        if ($eligibleTotal <= 0) {
            return;
        }

        $remaining = $discountAmount;
        $lastIndex = $indexes[array_key_last($indexes)];

        foreach ($indexes as $index) {
            if ($index === $lastIndex) {
                $lineDiscounts[$index] = round($remaining, 2);
                continue;
            }

            $share = round($discountAmount * ((float) $lineItems[$index]['amount'] / $eligibleTotal), 2);
            $lineDiscounts[$index] = $share;
            $remaining -= $share;
        }
    }
}
