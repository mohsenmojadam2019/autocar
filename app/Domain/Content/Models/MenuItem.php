<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['mobile_visible' => 'boolean', 'desktop_visible' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** Returns parent mega-menu item. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Returns child items in configured display order. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /** Indicates whether date scheduling currently permits display. */
    public function isCurrentlyVisible(): bool
    {
        return (! $this->starts_at || now()->gte($this->starts_at)) && (! $this->ends_at || now()->lte($this->ends_at));
    }
}
