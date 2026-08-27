<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeOption extends Model
{
    protected $guarded = [];

    /** Returns the attribute that owns this option. */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
