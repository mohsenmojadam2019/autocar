<?php

namespace App\Domain\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $guarded = [];

    /** Returns the immutable wallet ledger. */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }
}
