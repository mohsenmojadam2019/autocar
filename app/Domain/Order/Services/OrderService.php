<?php

namespace App\Domain\Order\Services;

use App\Domain\Notification\Services\OrderNotificationService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Services\CashbackService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function __construct(
        private readonly OrderNotificationService $notifications,
        private readonly CashbackService $cashback,
    ) {}

    /** Transitions an order through its state machine, writes history and emits post-commit side effects exactly once. */
    public function transition(Order $order, OrderStatus $next, ?string $note = null): Order
    {
        $current = $order->status;
        if ($current === $next) {
            return $order;
        }
        if (! $current->canTransitionTo($next)) {
            throw new RuntimeException("Invalid order transition {$current->value} -> {$next->value}");
        }

        $updated = DB::transaction(function () use ($order, $current, $next, $note): Order {
            $changes = ['status' => $next];
            if ($next === OrderStatus::Paid) {
                $changes['paid_at'] = now();
            }
            if ($next === OrderStatus::Delivered) {
                $changes['completed_at'] = now();
            }
            if ($next === OrderStatus::Cancelled) {
                $changes['cancelled_at'] = now();
            }
            $order->update($changes);
            $order->statusHistory()->create(['user_id' => auth()->id(), 'from_status' => $current->value, 'to_status' => $next->value, 'note' => $note, 'created_at' => now()]);

            return $order->fresh(['items', 'statusHistory', 'user']);
        });

        DB::afterCommit(function () use ($updated, $next): void {
            $this->notifications->statusChanged($updated, $next);
            if ($next === OrderStatus::Paid) {
                $this->cashback->grant($updated);
            }
        });

        return $updated;
    }

    /** Generates a collision-resistant human-readable order number. */
    public function nextNumber(): string
    {
        do {
            $number = 'AC-'.now()->format('ymd').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
