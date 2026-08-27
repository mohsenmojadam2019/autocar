<?php

namespace Tests\Unit;

use App\Domain\Order\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    /** Ensures paid orders cannot jump directly to delivered and terminal refunds cannot reopen. */
    public function test_state_machine_rejects_invalid_shortcuts(): void
    {
        self::assertFalse(OrderStatus::Paid->canTransitionTo(OrderStatus::Delivered));
        self::assertFalse(OrderStatus::Refunded->canTransitionTo(OrderStatus::Paid));
        self::assertTrue(OrderStatus::ReadyToShip->canTransitionTo(OrderStatus::Shipped));
    }
}
