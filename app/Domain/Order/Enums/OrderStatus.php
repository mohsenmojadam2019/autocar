<?php

namespace App\Domain\Order\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Reviewing = 'reviewing';
    case Sourcing = 'sourcing';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Refunded = 'refunded';

    /** Defines allowed state transitions to keep order lifecycle changes deterministic. */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::PendingPayment, self::Cancelled],
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Reviewing, self::Refunded, self::Cancelled],
            self::Reviewing => [self::Sourcing, self::ReadyToShip, self::Cancelled, self::Refunded],
            self::Sourcing => [self::ReadyToShip, self::Cancelled, self::Refunded],
            self::ReadyToShip => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered, self::Returned],
            self::Delivered => [self::Returned],
            self::Returned => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        }, true);
    }
}
