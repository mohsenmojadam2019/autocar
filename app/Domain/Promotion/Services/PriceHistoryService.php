<?php

namespace App\Domain\Promotion\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PriceHistoryService
{
    /** Captures changed money fields from a product/variant Eloquent update without modifying the source model. */
    public function capture(Product|ProductVariant $model, string $source = 'admin'): void
    {
        if (! Schema::hasTable('price_histories')) {
            return;
        }

        $productId = $model instanceof Product ? $model->id : $model->product_id;
        $variantId = $model instanceof ProductVariant ? $model->id : null;

        foreach (['purchase_price', 'sale_price', 'compare_at_price', 'wholesale_price'] as $field) {
            if (! $model->wasChanged($field)) {
                continue;
            }

            DB::table('price_histories')->insert([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'changed_by' => auth()->id(),
                'price_type' => $field,
                'old_value' => $this->money($model->getOriginal($field)),
                'new_value' => $this->money($model->getAttribute($field)),
                'source' => $source,
                'created_at' => now(),
            ]);
        }
    }

    private function money(mixed $value): ?int
    {
        return $value === null ? null : max(0, (int) $value);
    }
}
