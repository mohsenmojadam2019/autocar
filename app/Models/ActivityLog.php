<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'meta' => 'array', 'created_at' => 'datetime'];
    }

    /** Returns the authenticated actor when one existed. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Returns the domain record affected by the audited operation. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
