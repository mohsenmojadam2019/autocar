<?php

namespace App\Domain\Order\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Order\Models\Order;
use App\Domain\Promotion\Services\PricingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PhoneOrderService
{
    public function __construct(private readonly OrderService $orders, private readonly PricingService $pricing, private readonly InventoryReservationService $reservations) {}

    public function create(?int $userId, array $customer, array $lines, array $options = []): Order
    {
        if ($lines === []) {
            throw new RuntimeException('حداقل یک قلم سفارش لازم است.');
        }

        return DB::transaction(function () use ($userId, $customer, $lines, $options): Order {
            $resolved = collect($lines)->map(function (array $line) use ($userId): array {
                $product = Product::query()->published()->where('slug', $line['product_slug'])->firstOrFail();
                $variant = ! empty($line['variant_sku']) ? ProductVariant::query()->where('product_id', $product->id)->where('sku', $line['variant_sku'])->firstOrFail() : null;
                $quantity = max(1, (int) $line['quantity']);
                if ($product->maximum_order_quantity && $quantity > $product->maximum_order_quantity) {
                    throw new RuntimeException('تعداد سفارش «'.$product->name.'» از سقف مجاز بیشتر است.');
                }
                $price = $this->pricing->price($product, $variant, $quantity, $userId, false);

                return compact('product', 'variant', 'quantity', 'price');
            });
            $subtotal = (int) $resolved->sum(fn (array $line) => $line['price']['final_price'] * $line['quantity']);
            $tax = (int) $resolved->sum(fn (array $line) => $line['product']->is_taxable ? round(($line['price']['final_price'] * $line['quantity']) * ((float) $line['product']->tax_rate / 100)) : 0);
            $shipping = max(0, (int) ($options['shipping_total'] ?? 0));
            $discount = max(0, min($subtotal, (int) ($options['discount_total'] ?? 0)));
            $order = Order::query()->create(['number' => $this->orders->nextNumber(), 'user_id' => $userId, 'status' => 'pending_payment', 'source' => 'phone', 'subtotal' => $subtotal, 'discount_total' => $discount, 'shipping_total' => $shipping, 'tax_total' => $tax, 'grand_total' => max(0, $subtotal - $discount + $shipping + $tax), 'shipping_address' => $customer, 'billing_address' => $customer, 'invoice_kind' => $options['invoice_kind'] ?? 'natural', 'billing_profile_snapshot' => $options['billing_profile_snapshot'] ?? $customer, 'customer_note' => $options['customer_note'] ?? null, 'internal_note' => $options['internal_note'] ?? 'سفارش تلفنی ثبت‌شده توسط ادمین']);
            foreach ($resolved as $line) {
                $order->items()->create(['product_id' => $line['product']->id, 'product_variant_id' => $line['variant']?->id, 'sku' => $line['variant']?->sku ?? $line['product']->sku, 'name' => $line['product']->name, 'quantity' => $line['quantity'], 'unit_price' => $line['price']['final_price'], 'discount_total' => 0, 'tax_total' => $line['product']->is_taxable ? (int) round(($line['price']['final_price'] * $line['quantity']) * ((float) $line['product']->tax_rate / 100)) : 0, 'line_total' => $line['price']['final_price'] * $line['quantity'], 'snapshot' => ['slug' => $line['product']->slug, 'brand' => $line['product']->brand?->name, 'variant' => $line['variant']?->name, 'pricing' => $line['price']]]);
            }
            $this->reservations->reserveOrder($order, 60);
            $order->statusHistory()->create(['user_id' => auth()->id(), 'from_status' => null, 'to_status' => 'pending_payment', 'note' => 'سفارش تلفنی ایجاد شد.', 'created_at' => now()]);

            return $order->fresh(['items', 'statusHistory']);
        });
    }
}
