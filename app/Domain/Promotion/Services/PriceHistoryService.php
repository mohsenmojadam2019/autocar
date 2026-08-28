<?php

namespace App\Domain\Promotion\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PriceHistoryService
{
    /** Captures one immutable price snapshot whenever purchase/retail/wholesale pricing changes. */
    public function capture(Product|ProductVariant $model): void
    {
        if (! Schema::hasTable('price_histories') || ! $model->wasChanged(['purchase_price', 'sale_price', 'wholesale_price'])) {
            return;
        }

        DB::table('price_histories')->insert([
            'product_id' => $model instanceof Product ? $model->id : $model->product_id,
            'product_variant_id' => $model instanceof ProductVariant ? $model->id : null,
            'user_id' => auth()->id(),
            'purchase_price' => $this->money($model->purchase_price),
            'sale_price' => max(0, (int) $model->sale_price),
            'wholesale_price' => $this->money($model->wholesale_price),
            'starts_at' => now(),
            'ends_at' => null,
            'created_at' => now(),
        ]);
    }

    private function money(mixed $value): ?int
    {
        return $value === null ? null : max(0, (int) $value);
    }
}
