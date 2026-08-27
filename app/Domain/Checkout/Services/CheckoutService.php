<?php

namespace App\Domain\Checkout\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderService;
use App\Domain\Promotion\Models\Coupon;
use App\Domain\Promotion\Services\CouponService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutService
{
    public function __construct(private readonly OrderService $orders, private readonly CouponService $coupons) {}

    /** Converts a non-empty cart into an immutable pending-payment order snapshot. */
    public function createOrder(Cart $cart, array $shippingAddress, ?Coupon $coupon = null, int $shippingTotal = 0): Order
    {
        $cart->loadMissing(['items.product','items.variant']);
        if ($cart->status !== 'active' || $cart->items->isEmpty()) throw new RuntimeException('سبد خرید برای ثبت سفارش معتبر نیست.');
        $subtotal = $cart->subtotal();
        $discount = 0;
        if ($coupon) { $this->coupons->validate($coupon, $subtotal, $cart->user_id); $discount = $this->coupons->discount($coupon, $subtotal); }
        $tax = (int) $cart->items->sum(fn ($item) => $item->product->is_taxable ? round(($item->unit_price * $item->quantity) * ((float) $item->product->tax_rate / 100)) : 0);
        $grand = max(0, $subtotal - $discount + $shippingTotal + $tax);

        return DB::transaction(function () use ($cart, $shippingAddress, $coupon, $subtotal, $discount, $shippingTotal, $tax, $grand): Order {
            $order = Order::query()->create([
                'number'=>$this->orders->nextNumber(),'user_id'=>$cart->user_id,'cart_id'=>$cart->id,'status'=>'pending_payment','source'=>'web',
                'subtotal'=>$subtotal,'discount_total'=>$discount,'shipping_total'=>$shippingTotal,'tax_total'=>$tax,'grand_total'=>$grand,
                'shipping_address'=>$shippingAddress,'billing_address'=>$shippingAddress,'coupon_code'=>$coupon?->code,
            ]);
            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id'=>$item->product_id,'product_variant_id'=>$item->product_variant_id,'sku'=>$item->variant?->sku ?? $item->product->sku,
                    'name'=>$item->product->name,'quantity'=>$item->quantity,'unit_price'=>$item->unit_price,'discount_total'=>0,
                    'tax_total'=>$item->product->is_taxable ? (int) round(($item->unit_price*$item->quantity)*((float)$item->product->tax_rate/100)) : 0,
                    'line_total'=>$item->unit_price*$item->quantity,
                    'snapshot'=>['brand'=>$item->product->brand?->name,'oem_code'=>$item->product->oem_code,'authenticity'=>$item->product->authenticity?->value ?? null,'variant'=>$item->variant?->name],
                ]);
            }
            $cart->update(['status'=>'converted']);
            return $order->fresh('items');
        });
    }
}
