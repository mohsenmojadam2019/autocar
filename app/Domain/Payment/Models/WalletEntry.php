<?php

namespace App\Domain\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletEntry extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** Casts metadata used for audit-safe wallet references. */
    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    /** Returns the wallet whose balance changed in this ledger row. */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
