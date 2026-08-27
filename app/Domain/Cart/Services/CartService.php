<?php

namespace App\Domain\Cart\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CartService
{
    /** Creates or returns the active cart for a customer/guest token. */
    public function resolve(?int $userId, ?string $token): Cart
    {
        if ($token && $cart = Cart::query()->where('token', $token)->where('status', 'active')->first()) {
            if ($userId && ! $cart->user_id) $cart->update(['user_id' => $userId]);
            return $cart;
        }
        return Cart::query()->create(['token' => (string) Str::uuid(), 'user_id' => $userId, 'status' => 'active', 'expires_at' => now()->addDays(14), 'last_activity_at' => now()]);
    }

    /** Adds/increments a product while snapshotting the authoritative server-side price. */
    public function add(Cart $cart, Product $product, int $quantity = 1, ?ProductVariant $variant = null, array $meta = []): Cart
    {
        if ($quantity < 1) throw new RuntimeException('تعداد باید حداقل یک باشد.');
        if ($variant && (int) $variant->product_id !== (int) $product->id) throw new RuntimeException('تنوع انتخاب‌شده متعلق به این محصول نیست.');
        $max = $product->maximum_order_quantity ?: PHP_INT_MAX;

        DB::transaction(function () use ($cart, $product, $variant, $quantity, $meta, $max): void {
            $item = $cart->items()->where('product_id', $product->id)->where('product_variant_id', $variant?->id)->lockForUpdate()->first();
            $newQuantity = ($item?->quantity ?? 0) + $quantity;
            if ($newQuantity > $max) throw new RuntimeException('تعداد از سقف مجاز این کالا بیشتر است.');
            $price = (int) ($variant?->sale_price ?? $product->sale_price);
            if ($item) $item->update(['quantity' => $newQuantity, 'unit_price' => $price, 'meta' => $meta]);
            else $cart->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant?->id, 'quantity' => $newQuantity, 'unit_price' => $price, 'meta' => $meta]);
            $cart->update(['last_activity_at' => now(), 'expires_at' => now()->addDays(14)]);
        });

        return $cart->fresh('items.product');
    }

    /** Merges a guest cart into a signed-in customer's cart without duplicating identical SKUs. */
    public function merge(Cart $guest, Cart $customer): Cart
    {
        $guest->load('items.product', 'items.variant');
        foreach ($guest->items as $item) $this->add($customer, $item->product, $item->quantity, $item->variant, $item->meta ?? []);
        $guest->update(['status' => 'merged']);
        return $customer->fresh('items.product');
    }
}
