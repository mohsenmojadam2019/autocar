<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_comparable' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    /** Returns the presentation group that owns this technical attribute. */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AttributeGroup::class, 'attribute_group_id');
    }

    /** Returns predefined selectable values for select-like attributes. */
    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('position');
    }

    /** Returns categories that use this attribute in their specification template. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot(['is_required', 'position']);
    }
}
