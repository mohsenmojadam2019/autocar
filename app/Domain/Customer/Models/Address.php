<?php

namespace App\Domain\Customer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    /** Returns the customer who owns this saved delivery address. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
