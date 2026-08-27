<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Returns the parent catalog product for this purchasable SKU. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
