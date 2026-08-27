<?php

namespace App\Domain\Customer\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CompareList extends Model
{
    protected $guarded = [];

    /** Returns products in deterministic comparison order. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'compare_items')->withTimestamps()->orderBy('compare_items.id');
    }
}
