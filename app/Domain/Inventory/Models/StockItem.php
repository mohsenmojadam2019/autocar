<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItem extends Model
{
    protected $guarded = [];

    /** Returns the quantity that may still be sold after reservations and damaged units. */
    public function available(): int { return max(0, (int) $this->on_hand - (int) $this->reserved - (int) $this->damaged); }
    /** Returns the owning warehouse. */
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    /** Returns the stocked product. */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    /** Returns the exact variant when stock is variant-specific. */
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
