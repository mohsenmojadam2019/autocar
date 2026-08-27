<?php

namespace App\Domain\Promotion\Services;

use App\Domain\Promotion\Models\Coupon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CouponService
{
    /** Validates coupon activity, date, usage, customer and minimum-cart constraints. */
    public function validate(Coupon $coupon, int $subtotal, ?int $userId = null): void
    {
        if (! $coupon->is_active) throw new RuntimeException('این کد تخفیف غیرفعال است.');
        if ($coupon->starts_at && now()->lt($coupon->starts_at)) throw new RuntimeException('زمان استفاده از کد تخفیف هنوز شروع نشده است.');
        if ($coupon->ends_at && now()->gt($coupon->ends_at)) throw new RuntimeException('کد تخفیف منقضی شده است.');
        if ($subtotal < $coupon->minimum_subtotal) throw new RuntimeException('حداقل مبلغ سبد برای این کد تأمین نشده است.');
        if ($coupon->usage_limit && DB::table('coupon_redemptions')->where('coupon_id', $coupon->id)->count() >= $coupon->usage_limit) throw new RuntimeException('ظرفیت استفاده از کد تخفیف تکمیل شده است.');
        if ($userId && $coupon->per_user_limit && DB::table('coupon_redemptions')->where('coupon_id', $coupon->id)->where('user_id', $userId)->count() >= $coupon->per_user_limit) throw new RuntimeException('سقف استفاده شما از این کد تکمیل شده است.');
        if ($userId && $coupon->first_order_only && DB::table('orders')->where('user_id', $userId)->whereNotIn('status', ['cancelled'])->exists()) throw new RuntimeException('این کد فقط برای اولین سفارش قابل استفاده است.');
    }

    /** Calculates fixed/percentage coupon discount and applies the configured cap. */
    public function discount(Coupon $coupon, int $subtotal): int
    {
        $raw = match ($coupon->type) {
            'fixed' => (int) $coupon->value,
            'percentage' => (int) round($subtotal * ((float) $coupon->value / 100)),
            default => 0,
        };
        if ($coupon->max_discount) $raw = min($raw, (int) $coupon->max_discount);
        return min($subtotal, max(0, $raw));
    }
}
