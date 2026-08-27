<?php

namespace App\Domain\Wallet\Models;

use Illuminate\Database\Eloquent\Model;

class WalletEntry extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }
}
