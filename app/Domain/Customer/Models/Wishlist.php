<?php

namespace App\Domain\Customer\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Wishlist extends Model
{
    protected $guarded = [];

    /** Returns products saved to this customer list. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlist_items')->withTimestamps();
    }
}
