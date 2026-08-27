<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeGroup extends Model
{
    protected $guarded = [];

    /** Returns technical attributes in deterministic admin/display order. */
    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class)->orderBy('position');
    }
}
