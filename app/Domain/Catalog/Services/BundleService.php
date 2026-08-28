<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductBundle;
use App\Domain\Promotion\Services\PricingService;
use Illuminate\Support\Collection;

class BundleService
{
    public function __construct(private readonly PricingService $pricing) {}

    /** Returns active bundle offers containing the current product with authoritative item prices. */
    public function forProduct(Product $product): Collection
    {
        return ProductBundle::query()->running()->whereHas('products', fn ($q) => $q->whereKey($product->id))->with(['products.brand', 'products.media'])->get()->map(function (ProductBundle $bundle): array {
            $items = $bundle->products->map(function (Product $item): array {
                $quantity = max(1, (int) $item->pivot->quantity);
                $price = $this->pricing->price($item, null, $quantity);

                return ['product' => $item, 'quantity' => $quantity, 'unit_price' => $price['final_price'], 'line_total' => $price['final_price'] * $quantity];
            });
            $subtotal = (int) $items->sum('line_total');
            $discount = $bundle->discount_type === 'fixed'
                ? min($subtotal, (int) $bundle->discount_value)
                : (int) round($subtotal * min(max((float) $bundle->discount_value, 0), 100) / 100);

            return ['bundle' => $bundle, 'items' => $items, 'subtotal' => $subtotal, 'discount' => $discount, 'total' => max(0, $subtotal - $discount)];
        });
    }
}
