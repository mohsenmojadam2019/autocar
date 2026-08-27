<?php

namespace App\Domain\Promotion\Models;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AutomaticPromotion extends Model
{
    protected $guarded = [];

    /** Casts date windows and rule metadata for deterministic promotion evaluation. */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'is_active' => 'boolean',
            'stackable' => 'boolean',
            'conditions' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** Limits queries to promotions whose activation window includes the current time. */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $builder) => $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Returns products explicitly included in this promotion. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'automatic_promotion_product');
    }

    /** Returns categories explicitly included in this promotion. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'automatic_promotion_category');
    }

    /** Returns brands explicitly included in this promotion. */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'automatic_promotion_brand');
    }
}
