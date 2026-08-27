<?php

namespace App\Domain\Payment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $guarded = [];

    /** Returns the customer owning this wallet. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Returns the immutable wallet ledger in newest-first order. */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class)->latest('id');
    }
}
