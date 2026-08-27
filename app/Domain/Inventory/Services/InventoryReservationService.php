<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Models\StockItem;
use App\Domain\Order\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryReservationService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** Allocates each order line across active warehouses and creates expiring reservations under stock locks. */
    public function reserveOrder(Order $order, int $minutes = 20): void
    {
        $order->loadMissing('items');

        DB::transaction(function () use ($order, $minutes): void {
            foreach ($order->items as $orderItem) {
                if (! $orderItem->product_id) {
                    throw new RuntimeException('محصول سفارش برای رزرو موجودی قابل شناسایی نیست.');
                }

                $remaining = (int) $orderItem->quantity;
                $stocks = StockItem::query()
                    ->join('warehouses', 'warehouses.id', '=', 'stock_items.warehouse_id')
                    ->where('warehouses.is_active', true)
                    ->where('stock_items.product_id', $orderItem->product_id)
                    ->when(
                        $orderItem->product_variant_id,
                        fn ($query, $variantId) => $query->where('stock_items.product_variant_id', $variantId),
                        fn ($query) => $query->whereNull('stock_items.product_variant_id'),
                    )
                    ->select('stock_items.*')
                    ->orderByDesc(DB::raw('(stock_items.on_hand - stock_items.reserved - stock_items.damaged)'))
                    ->lockForUpdate()
                    ->get();

                foreach ($stocks as $stock) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $available = $stock->available();
                    if ($available <= 0) {
                        continue;
                    }
                    $quantity = min($remaining, $available);
                    $this->inventory->reserve($stock->id, $quantity, Order::class, $order->id);
                    InventoryReservation::query()->create([
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'stock_item_id' => $stock->id,
                        'quantity' => $quantity,
                        'status' => 'reserved',
                        'expires_at' => now()->addMinutes($minutes),
                    ]);
                    $remaining -= $quantity;
                }

                if ($remaining > 0) {
                    throw new RuntimeException('موجودی کافی برای «'.$orderItem->name.'» وجود ندارد.');
                }
            }
        });
    }

    /** Commits all active reservations into physical sale movements after authoritative payment confirmation. */
    public function commitOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $reservations = InventoryReservation::query()
                ->where('order_id', $order->id)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $this->inventory->commitReservation(
                    $reservation->stock_item_id,
                    $reservation->quantity,
                    Order::class,
                    $order->id,
                );
                $reservation->update([
                    'status' => 'committed',
                    'committed_at' => now(),
                ]);
            }
        });
    }

    /** Releases all active reservations when payment fails, an order is cancelled, or reservation time expires. */
    public function releaseOrder(Order $order, string $status = 'released'): void
    {
        DB::transaction(function () use ($order, $status): void {
            $reservations = InventoryReservation::query()
                ->where('order_id', $order->id)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $this->inventory->release(
                    $reservation->stock_item_id,
                    $reservation->quantity,
                    Order::class,
                    $order->id,
                );
                $reservation->update([
                    'status' => $status,
                    'released_at' => now(),
                ]);
            }
        });
    }

    /** Releases expired pending-payment reservations in bounded batches and returns the number processed. */
    public function releaseExpired(int $limit = 200): int
    {
        $orderIds = InventoryReservation::query()
            ->where('status', 'reserved')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->pluck('order_id')
            ->unique();

        $processed = 0;
        foreach ($orderIds as $orderId) {
            $order = Order::query()->find($orderId);
            if (! $order) {
                continue;
            }
            $this->releaseOrder($order, 'expired');
            $processed++;
        }

        return $processed;
    }
}
