<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Returns the direct parent used to form the unlimited category tree. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Returns direct children ordered exactly as configured in the admin. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /** Returns every product assigned to this category. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('is_primary');
    }

    /** Limits storefront queries to categories currently allowed for display. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
