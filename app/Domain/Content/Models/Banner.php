<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $guarded = [];

    /** Casts schedule and activation values used by storefront placement queries. */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** Limits banners to records active in the current schedule window. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $builder) => $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Limits banners to one named storefront placement and deterministic display order. */
    public function scopePlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement)->orderBy('position')->orderBy('id');
    }
}
