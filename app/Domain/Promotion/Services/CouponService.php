<?php

namespace App\Domain\Promotion\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Promotion\Models\Coupon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CouponService
{
    /** Validates coupon activity, dates, usage, customer and minimum-cart constraints. */
    public function validate(Coupon $coupon, int $subtotal, ?int $userId = null): void
    {
        if (! $coupon->is_active) {
            throw new RuntimeException('این کد تخفیف غیرفعال است.');
        }
        if ($coupon->starts_at && now()->lt($coupon->starts_at)) {
            throw new RuntimeException('زمان استفاده از کد تخفیف هنوز شروع نشده است.');
        }
        if ($coupon->ends_at && now()->gt($coupon->ends_at)) {
            throw new RuntimeException('کد تخفیف منقضی شده است.');
        }
        if ($subtotal < $coupon->minimum_subtotal) {
            throw new RuntimeException('حداقل مبلغ سبد برای این کد تأمین نشده است.');
        }
        if ($coupon->usage_limit && DB::table('coupon_redemptions')->where('coupon_id', $coupon->id)->count() >= $coupon->usage_limit) {
            throw new RuntimeException('ظرفیت استفاده از کد تخفیف تکمیل شده است.');
        }
        if ($userId && $coupon->per_user_limit && DB::table('coupon_redemptions')->where('coupon_id', $coupon->id)->where('user_id', $userId)->count() >= $coupon->per_user_limit) {
            throw new RuntimeException('سقف استفاده شما از این کد تکمیل شده است.');
        }
        if ($userId && $coupon->first_order_only && DB::table('orders')->where('user_id', $userId)->whereNotIn('status', ['cancelled'])->exists()) {
            throw new RuntimeException('این کد فقط برای اولین سفارش قابل استفاده است.');
        }
    }

    /** Calculates a basic fixed/percentage discount against one already-eligible subtotal. */
    public function discount(Coupon $coupon, int $subtotal): int
    {
        $raw = match ($coupon->type) {
            'fixed' => (int) $coupon->value,
            'percentage' => (int) round($subtotal * ((float) $coupon->value / 100)),
            default => 0,
        };

        return $this->cap($coupon, $subtotal, $raw);
    }

    /** Calculates the full cart discount while enforcing product/category scope and BOGO conditions. */
    public function discountForCart(Coupon $coupon, Cart $cart): int
    {
        $cart->loadMissing(['items.product.categories']);
        $subtotal = $cart->subtotal();
        $this->validate($coupon, $subtotal, $cart->user_id);
        $eligible = $this->eligibleItems($coupon, $cart);
        if ($eligible->isEmpty() && $this->hasCatalogScope($coupon)) {
            throw new RuntimeException('این کد برای کالاهای سبد فعلی قابل استفاده نیست.');
        }

        $eligibleSubtotal = (int) $eligible->sum(fn ($item) => $item->unit_price * $item->quantity);
        $raw = match ($coupon->type) {
            'fixed' => min((int) $coupon->value, $eligibleSubtotal),
            'percentage' => (int) round($eligibleSubtotal * ((float) $coupon->value / 100)),
            'bogo' => $this->bogoDiscount($coupon, $eligible),
            default => 0,
        };

        return $this->cap($coupon, $subtotal, $raw);
    }

    /** Returns true when the coupon removes the authoritative shipping charge for this eligible cart. */
    public function grantsFreeShipping(Coupon $coupon, Cart $cart): bool
    {
        if ($coupon->type !== 'free_shipping') {
            return false;
        }
        $this->validate($coupon, $cart->subtotal(), $cart->user_id);
        $eligible = $this->eligibleItems($coupon, $cart);

        return ! $this->hasCatalogScope($coupon) || $eligible->isNotEmpty();
    }

    /** Records a successful coupon use only after an order snapshot exists. */
    public function redeem(Coupon $coupon, int $orderId, ?int $userId, int $discountAmount): void
    {
        DB::table('coupon_redemptions')->insert([
            'coupon_id' => $coupon->id,
            'user_id' => $userId,
            'order_id' => $orderId,
            'discount_amount' => max(0, $discountAmount),
            'created_at' => now(),
        ]);
    }

    /** Returns cart lines eligible under product/category coupon pivots; unscoped coupons include all lines. */
    private function eligibleItems(Coupon $coupon, Cart $cart): Collection
    {
        $productIds = DB::table('coupon_product')->where('coupon_id', $coupon->id)->pluck('product_id')->all();
        $categoryIds = DB::table('coupon_category')->where('coupon_id', $coupon->id)->pluck('category_id')->all();
        if ($productIds === [] && $categoryIds === []) {
            return $cart->items;
        }

        return $cart->items->filter(function ($item) use ($productIds, $categoryIds): bool {
            if (in_array((int) $item->product_id, $productIds, true)) {
                return true;
            }

            return $item->product->categories->pluck('id')->intersect($categoryIds)->isNotEmpty();
        })->values();
    }

    /** Determines whether a coupon has explicit catalog scope restrictions. */
    private function hasCatalogScope(Coupon $coupon): bool
    {
        return DB::table('coupon_product')->where('coupon_id', $coupon->id)->exists()
            || DB::table('coupon_category')->where('coupon_id', $coupon->id)->exists();
    }

    /** Calculates buy-X-get-Y discounts against the cheapest eligible units in each completed promotion group. */
    private function bogoDiscount(Coupon $coupon, Collection $items): int
    {
        $conditions = $coupon->conditions ?? [];
        $buyQuantity = max(1, (int) ($conditions['buy_quantity'] ?? 1));
        $getQuantity = max(1, (int) ($conditions['get_quantity'] ?? 1));
        $getPercent = min(100, max(1, (int) ($conditions['get_discount_percent'] ?? 100)));
        $unitPrices = [];
        foreach ($items as $item) {
            for ($index = 0; $index < (int) $item->quantity; $index++) {
                $unitPrices[] = (int) $item->unit_price;
            }
        }
        sort($unitPrices, SORT_NUMERIC);
        $groupSize = $buyQuantity + $getQuantity;
        $discountedUnits = intdiv(count($unitPrices), $groupSize) * $getQuantity;
        if ($discountedUnits < 1) {
            return 0;
        }

        return (int) round(array_sum(array_slice($unitPrices, 0, $discountedUnits)) * ($getPercent / 100));
    }

    /** Applies configured maximum discount and prevents discounts exceeding the order subtotal. */
    private function cap(Coupon $coupon, int $subtotal, int $discount): int
    {
        if ($coupon->max_discount) {
            $discount = min($discount, (int) $coupon->max_discount);
        }

        return min($subtotal, max(0, $discount));
    }
}
