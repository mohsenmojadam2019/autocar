<?php

namespace App\Domain\Cart\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Promotion\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CartService
{
    public function __construct(private readonly PricingService $pricing) {}

    /** Creates or returns the active cart for a customer/guest token. */
    public function resolve(?int $userId, ?string $token): Cart
    {
        if ($token && $cart = Cart::query()->where('token', $token)->where('status', 'active')->first()) {
            if ($userId && ! $cart->user_id) {
                $cart->update(['user_id' => $userId]);
            }

            return $cart;
        }

        if ($userId && $cart = Cart::query()->where('user_id', $userId)->where('status', 'active')->latest('id')->first()) {
            return $cart;
        }

        return Cart::query()->create([
            'token' => (string) Str::uuid(),
            'user_id' => $userId,
            'status' => 'active',
            'expires_at' => now()->addDays(14),
            'last_activity_at' => now(),
        ]);
    }

    /** Claims the browser guest cart after login and merges it into an existing customer cart when needed. */
    public function claimAfterLogin(int $userId, ?string $sessionToken): Cart
    {
        return DB::transaction(function () use ($userId, $sessionToken): Cart {
            $customer = Cart::query()->where('user_id', $userId)->where('status', 'active')->latest('id')->lockForUpdate()->first();
            $sessionCart = $sessionToken
                ? Cart::query()->where('token', $sessionToken)->where('status', 'active')->lockForUpdate()->first()
                : null;

            if ($sessionCart && $sessionCart->user_id && (int) $sessionCart->user_id !== $userId) {
                $sessionCart = null;
            }

            if (! $customer && $sessionCart) {
                $sessionCart->update(['user_id' => $userId, 'last_activity_at' => now(), 'expires_at' => now()->addDays(14)]);

                return $sessionCart->fresh('items.product');
            }

            $customer ??= $this->resolve($userId, null);
            if ($sessionCart && (int) $sessionCart->id !== (int) $customer->id) {
                return $this->merge($sessionCart, $customer);
            }

            return $customer;
        });
    }

    /** Adds/increments a product while snapshotting the authoritative server-side timed price. */
    public function add(Cart $cart, Product $product, int $quantity = 1, ?ProductVariant $variant = null, array $meta = []): Cart
    {
        if ($quantity < 1) {
            throw new RuntimeException('تعداد باید حداقل یک باشد.');
        }
        if ($variant && (int) $variant->product_id !== (int) $product->id) {
            throw new RuntimeException('تنوع انتخاب‌شده متعلق به این محصول نیست.');
        }

        $max = $product->maximum_order_quantity ?: PHP_INT_MAX;
        DB::transaction(function () use ($cart, $product, $variant, $quantity, $meta, $max): void {
            $item = $cart->items()->where('product_id', $product->id)->where('product_variant_id', $variant?->id)->lockForUpdate()->first();
            $newQuantity = ($item?->quantity ?? 0) + $quantity;
            if ($newQuantity > $max) {
                throw new RuntimeException('تعداد از سقف مجاز این کالا بیشتر است.');
            }
            $price = $this->pricing->price($product, $variant, $newQuantity);
            $snapshotMeta = array_merge($meta, ['pricing' => $this->pricingMeta($price)]);
            if ($item) {
                $item->update(['quantity' => $newQuantity, 'unit_price' => $price['final_price'], 'meta' => $snapshotMeta]);
            } else {
                $cart->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant?->id, 'quantity' => $newQuantity, 'unit_price' => $price['final_price'], 'meta' => $snapshotMeta]);
            }
            $cart->update(['last_activity_at' => now(), 'expires_at' => now()->addDays(14)]);
        });

        return $cart->fresh('items.product');
    }

    /** Reprices a cart line authoritatively whenever quantity changes. */
    public function updateQuantity(Cart $cart, int $itemId, int $quantity): CartItem
    {
        if ($quantity < 1) {
            throw new RuntimeException('تعداد باید حداقل یک باشد.');
        }

        return DB::transaction(function () use ($cart, $itemId, $quantity): CartItem {
            $line = $cart->items()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $line->loadMissing(['product', 'variant']);
            $max = $line->product->maximum_order_quantity ?: PHP_INT_MAX;
            if ($quantity > $max) {
                throw new RuntimeException('تعداد از سقف مجاز این کالا بیشتر است.');
            }
            $price = $this->pricing->price($line->product, $line->variant, $quantity);
            $meta = array_merge($line->meta ?? [], ['pricing' => $this->pricingMeta($price)]);
            $line->update(['quantity' => $quantity, 'unit_price' => $price['final_price'], 'meta' => $meta]);
            $cart->update(['last_activity_at' => now(), 'expires_at' => now()->addDays(14)]);

            return $line->fresh(['product', 'variant']);
        });
    }

    /** Merges a guest cart into a signed-in customer's cart without duplicating identical SKUs. */
    public function merge(Cart $guest, Cart $customer): Cart
    {
        $guest->load('items.product', 'items.variant');
        foreach ($guest->items as $item) {
            $this->add($customer, $item->product, $item->quantity, $item->variant, $item->meta ?? []);
        }
        $guest->update(['status' => 'merged']);

        return $customer->fresh('items.product');
    }

    private function pricingMeta(array $price): array
    {
        return [
            'base_price' => $price['base_price'],
            'promotion_id' => $price['promotion_id'],
            'promotion_name' => $price['promotion_name'],
            'discount_amount' => $price['discount_amount'],
            'expires_at' => $price['ends_at']?->toIso8601String(),
        ];
    }
}
