<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRelation extends Model
{
    public $incrementing = false;
    public $timestamps = false;

    protected $table = 'product_relations';
    protected $guarded = [];

    /** Returns the source product for this relation. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Returns the related, complementary, alternative or upsell product. */
    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}
