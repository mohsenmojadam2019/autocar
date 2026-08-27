<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /** Uses a readable slug for all brand route-model binding. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Returns products manufactured or distributed under this brand. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Limits queries to brands that may be displayed in the storefront. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
