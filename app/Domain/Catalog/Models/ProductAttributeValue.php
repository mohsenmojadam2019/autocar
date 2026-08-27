<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
    protected $guarded = [];

    /** Returns the product that owns this specification value. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Returns the specification definition. */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /** Returns the selected predefined option when this value uses one. */
    public function option(): BelongsTo
    {
        return $this->belongsTo(AttributeOption::class, 'attribute_option_id');
    }
}
