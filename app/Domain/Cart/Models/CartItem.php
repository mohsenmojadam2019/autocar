<?php

namespace App\Domain\Cart\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['meta' => 'array']; }
    /** Returns the owning cart. */ public function cart(): BelongsTo { return $this->belongsTo(Cart::class); }
    /** Returns the product. */ public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    /** Returns the selected variant when applicable. */ public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
