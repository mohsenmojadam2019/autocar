<?php

namespace App\Domain\Checkout\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderService;
use App\Domain\Payment\Services\WalletService;
use App\Domain\Promotion\Models\Coupon;
use App\Domain\Promotion\Services\CouponService;
use App\Domain\Promotion\Services\PricingService;
use App\Domain\Shipping\Services\ShippingRateService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly CouponService $coupons,
        private readonly PricingService $pricing,
        private readonly ShippingRateService $shipping,
        private readonly InventoryReservationService $reservations,
        private readonly WalletService $wallets,
    ) {}

    /** Converts a cart into an immutable order after repricing, shipping validation, coupon rules, wallet use and stock reservation. */
    public function createOrder(
        Cart $cart,
        array $shippingAddress,
        ?Coupon $coupon = null,
        ?int $shippingMethodId = null,
        bool $useWallet = false,
    ): Order {
        $cart->loadMissing(['items.product.brand', 'items.product.categories', 'items.variant']);
        if ($cart->status !== 'active' || $cart->items->isEmpty()) {
            throw new RuntimeException('سبد خرید برای ثبت سفارش معتبر نیست.');
        }

        return DB::transaction(function () use ($cart, $shippingAddress, $coupon, $shippingMethodId, $useWallet): Order {
            foreach ($cart->items as $item) {
                $snapshot = $this->pricing->price($item->product, $item->variant, (int) $item->quantity);
                $item->unit_price = $snapshot['final_price'];
                $item->meta = array_merge($item->meta ?? [], ['pricing' => [
                    'base_price' => $snapshot['base_price'],
                    'promotion_id' => $snapshot['promotion_id'],
                    'promotion_name' => $snapshot['promotion_name'],
                    'discount_amount' => $snapshot['discount_amount'],
                    'expires_at' => $snapshot['ends_at']?->toIso8601String(),
                ]]);
                $item->save();
            }

            $subtotal = $cart->subtotal();
            $discount = $coupon ? $this->coupons->discountForCart($coupon, $cart) : 0;
            $tax = (int) $cart->items->sum(fn ($item) => $item->product->is_taxable
                ? round(($item->unit_price * $item->quantity) * ((float) $item->product->tax_rate / 100))
                : 0);
            $weight = (int) $cart->items->sum(fn ($item) => ((int) ($item->product->weight_grams ?? 0)) * (int) $item->quantity);
            $rates = $this->shipping->rates(
                (string) ($shippingAddress['province'] ?? ''),
                $shippingAddress['city'] ?? null,
                $weight,
                max(0, $subtotal - $discount),
            );
            $selectedRate = $shippingMethodId ? $rates->firstWhere('id', $shippingMethodId) : null;
            if ($rates->isNotEmpty() && ! $selectedRate) {
                throw new RuntimeException('روش ارسال معتبر را انتخاب کنید.');
            }
            $shippingTotal = (int) ($selectedRate?->price ?? 0);
            if ($coupon && $this->coupons->grantsFreeShipping($coupon, $cart)) {
                $shippingTotal = 0;
            }
            $grand = max(0, $subtotal - $discount + $shippingTotal + $tax);

            $order = Order::query()->create([
                'number' => $this->orders->nextNumber(),
                'user_id' => $cart->user_id,
                'cart_id' => $cart->id,
                'shipping_method_id' => $selectedRate?->id,
                'status' => 'pending_payment',
                'source' => 'web',
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'wallet_total' => 0,
                'shipping_total' => $shippingTotal,
                'tax_total' => $tax,
                'grand_total' => $grand,
                'shipping_address' => $shippingAddress,
                'billing_address' => $shippingAddress,
                'coupon_code' => $coupon?->code,
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'sku' => $item->variant?->sku ?? $item->product->sku,
                    'name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_total' => 0,
                    'tax_total' => $item->product->is_taxable
                        ? (int) round(($item->unit_price * $item->quantity) * ((float) $item->product->tax_rate / 100))
                        : 0,
                    'line_total' => $item->unit_price * $item->quantity,
                    'snapshot' => [
                        'brand' => $item->product->brand?->name,
                        'oem_code' => $item->product->oem_code,
                        'authenticity' => $item->product->authenticity?->value ?? null,
                        'variant' => $item->variant?->name,
                        'pricing' => $item->meta['pricing'] ?? null,
                    ],
                ]);
            }

            $this->reservations->reserveOrder($order);

            if ($useWallet && $order->user_id && $grand > 0) {
                $walletUsed = $this->wallets->debit(
                    $order->user_id,
                    $grand,
                    Order::class,
                    $order->id,
                    'پرداخت سفارش '.$order->number,
                );
                $order->update(['wallet_total' => $walletUsed]);
            }

            if ($coupon) {
                $this->coupons->redeem($coupon, $order->id, $cart->user_id, $discount);
            }
            $cart->update(['status' => 'converted']);

            return $order->fresh(['items', 'payments']);
        });
    }
}
