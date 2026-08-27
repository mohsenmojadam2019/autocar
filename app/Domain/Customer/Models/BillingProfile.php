<?php

namespace App\Domain\Customer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** Returns the customer that owns this invoice identity. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Returns the immutable-safe array copied into each order/invoice. */
    public function snapshot(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'full_name' => $this->full_name,
            'national_code' => $this->national_code,
            'company_name' => $this->company_name,
            'national_id' => $this->national_id,
            'economic_code' => $this->economic_code,
            'registration_number' => $this->registration_number,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'province' => $this->province,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'address' => $this->address,
        ];
    }
}
