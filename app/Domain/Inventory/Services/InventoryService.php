<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\StockItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    /** Reserves sellable stock under a row lock to prevent overselling during concurrent checkouts. */
    public function reserve(int $stockItemId, int $quantity, ?string $referenceType = null, ?int $referenceId = null): StockItem
    {
        if ($quantity < 1) {
            throw new RuntimeException('Reserve quantity must be positive.');
        }

        return DB::transaction(function () use ($stockItemId, $quantity, $referenceType, $referenceId): StockItem {
            $stock = StockItem::query()->lockForUpdate()->findOrFail($stockItemId);
            if ($stock->available() < $quantity) {
                throw new RuntimeException('موجودی کافی نیست.');
            }
            $stock->increment('reserved', $quantity);
            $this->movement($stock->fresh(), 'reserve', 0, $referenceType, $referenceId, ['reserved_delta' => $quantity]);

            return $stock->fresh();
        });
    }

    /** Releases a previous reservation without changing physical on-hand quantity. */
    public function release(int $stockItemId, int $quantity, ?string $referenceType = null, ?int $referenceId = null): StockItem
    {
        return DB::transaction(function () use ($stockItemId, $quantity, $referenceType, $referenceId): StockItem {
            $stock = StockItem::query()->lockForUpdate()->findOrFail($stockItemId);
            $stock->reserved = max(0, (int) $stock->reserved - max(0, $quantity));
            $stock->save();
            $this->movement($stock, 'release', 0, $referenceType, $referenceId, ['reserved_delta' => -$quantity]);

            return $stock->fresh();
        });
    }

    /** Converts reserved units into a physical stock-out after order payment/fulfilment. */
    public function commitReservation(int $stockItemId, int $quantity, ?string $referenceType = null, ?int $referenceId = null): StockItem
    {
        return DB::transaction(function () use ($stockItemId, $quantity, $referenceType, $referenceId): StockItem {
            $stock = StockItem::query()->lockForUpdate()->findOrFail($stockItemId);
            if ($stock->reserved < $quantity || $stock->on_hand < $quantity) {
                throw new RuntimeException('رزرو موجودی نامعتبر است.');
            }
            $stock->reserved -= $quantity;
            $stock->on_hand -= $quantity;
            $stock->save();
            $this->movement($stock, 'sale', -$quantity, $referenceType, $referenceId);

            return $stock->fresh();
        });
    }

    /** Applies an audited manual stock adjustment with an explicit reason. */
    public function adjust(int $stockItemId, int $delta, string $reason): StockItem
    {
        if ($delta === 0 || trim($reason) === '') {
            throw new RuntimeException('برای اصلاح موجودی مقدار و دلیل معتبر لازم است.');
        }

        return DB::transaction(function () use ($stockItemId, $delta, $reason): StockItem {
            $stock = StockItem::query()->lockForUpdate()->findOrFail($stockItemId);
            if ($stock->on_hand + $delta < 0) {
                throw new RuntimeException('موجودی نمی‌تواند منفی شود.');
            }
            $stock->increment('on_hand', $delta);
            $this->movement($stock->fresh(), 'adjustment', $delta, null, null, ['reason' => $reason]);

            return $stock->fresh();
        });
    }

    /** Persists an immutable stock-ledger row for audit and reporting. */
    private function movement(StockItem $stock, string $type, int $quantity, ?string $referenceType = null, ?int $referenceId = null, array $meta = []): void
    {
        DB::table('stock_movements')->insert([
            'stock_item_id' => $stock->id, 'user_id' => auth()->id(), 'type' => $type, 'quantity' => $quantity,
            'balance_after' => $stock->on_hand, 'reference_type' => $referenceType, 'reference_id' => $referenceId,
            'reason' => $meta['reason'] ?? null, 'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, 'created_at' => now(),
        ]);
    }
}
