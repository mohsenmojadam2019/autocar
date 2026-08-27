<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\AuthenticityType;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Vehicle\Models\ProductFitment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /** Uses the immutable public slug for every implicit route-model binding. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'authenticity' => AuthenticityType::class,
            'status' => ProductStatus::class,
            'is_taxable' => 'boolean',
            'tax_rate' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    /** Returns the optional parts brand assigned to this product. */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** Returns catalog categories including the primary-category marker. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('is_primary');
    }

    /** Returns purchasable SKU variants ordered for deterministic display. */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    /** Returns local product images and documents in presentation order. */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('position');
    }

    /** Returns typed specification values shown in filters, comparison and details. */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /** Returns all vehicle compatibility rules defined for this product. */
    public function fitments(): HasMany
    {
        return $this->hasMany(ProductFitment::class);
    }

    /** Returns outgoing related/complementary/alternative/upsell relationships. */
    public function relations(): HasMany
    {
        return $this->hasMany(ProductRelation::class);
    }

    /** Limits storefront queries to published and active products. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active)
            ->where(fn (Builder $builder) => $builder
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    /** Returns the currently effective display price before cart-specific promotions. */
    public function effectivePrice(): int
    {
        return (int) $this->sale_price;
    }
}
