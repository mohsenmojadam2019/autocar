<?php

namespace App\Domain\Order\Models;

use App\Domain\Customer\Models\BillingProfile;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Shipping\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'billing_profile_snapshot' => 'array',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** Returns the customer who owns this order. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Returns the mutable source billing profile; historical documents use the stored snapshot instead. */
    public function billingProfile(): BelongsTo
    {
        return $this->belongsTo(BillingProfile::class);
    }

    /** Returns immutable line-item snapshots. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Returns the full lifecycle timeline. */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /** Returns every payment attempt associated with this order. */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /** Returns issued invoice documents. */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Returns fulfilment shipments and tracking state. */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** Returns RMA requests opened for this order. */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }
}
