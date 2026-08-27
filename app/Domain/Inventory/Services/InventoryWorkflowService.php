<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\StockItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryWorkflowService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** Transfers physical stock atomically between warehouses and records an auditable transfer document. */
    public function transfer(int $fromWarehouseId, int $toWarehouseId, array $items, ?string $note = null): int
    {
        if ($fromWarehouseId === $toWarehouseId || $items === []) {
            throw new RuntimeException('مبدا، مقصد و اقلام انتقال معتبر نیستند.');
        }

        return DB::transaction(function () use ($fromWarehouseId, $toWarehouseId, $items, $note): int {
            $transferId = (int) DB::table('stock_transfers')->insertGetId([
                'number' => 'TR-'.now()->format('ymdHis').'-'.random_int(100, 999),
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'created_by' => auth()->id(),
                'status' => 'completed',
                'note' => $note,
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $variantId = isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
                $quantity = (int) $item['quantity'];
                if ($quantity < 1) {
                    throw new RuntimeException('تعداد انتقال باید مثبت باشد.');
                }

                $source = StockItem::query()->where('warehouse_id', $fromWarehouseId)->where('product_id', $productId)
                    ->when($variantId, fn ($query) => $query->where('product_variant_id', $variantId), fn ($query) => $query->whereNull('product_variant_id'))
                    ->lockForUpdate()->firstOrFail();
                if ($source->available() < $quantity) {
                    throw new RuntimeException('موجودی قابل انتقال کافی نیست.');
                }

                $destination = StockItem::query()->firstOrCreate([
                    'warehouse_id' => $toWarehouseId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                ], ['on_hand' => 0, 'reserved' => 0, 'damaged' => 0, 'reorder_point' => 0]);

                $this->inventory->adjust($source->id, -$quantity, 'انتقال انبار '.$transferId);
                $this->inventory->adjust($destination->id, $quantity, 'دریافت انتقال انبار '.$transferId);
                DB::table('stock_transfer_items')->insert([
                    'stock_transfer_id' => $transferId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $transferId;
        });
    }

    /** Reconciles a physical stock count against ledger quantity and posts only the required deltas. */
    public function count(int $warehouseId, array $counts, ?string $note = null): int
    {
        return DB::transaction(function () use ($warehouseId, $counts, $note): int {
            $countId = (int) DB::table('stock_counts')->insertGetId([
                'number' => 'SC-'.now()->format('ymdHis').'-'.random_int(100, 999),
                'warehouse_id' => $warehouseId,
                'created_by' => auth()->id(),
                'status' => 'completed',
                'note' => $note,
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($counts as $stockItemId => $counted) {
                $stock = StockItem::query()->where('warehouse_id', $warehouseId)->lockForUpdate()->findOrFail((int) $stockItemId);
                $expected = (int) $stock->on_hand;
                $counted = max(0, (int) $counted);
                $difference = $counted - $expected;
                if ($difference !== 0) {
                    $this->inventory->adjust($stock->id, $difference, 'شمارش انبار '.$countId);
                }
                DB::table('stock_count_items')->insert([
                    'stock_count_id' => $countId,
                    'stock_item_id' => $stock->id,
                    'expected_quantity' => $expected,
                    'counted_quantity' => $counted,
                    'difference' => $difference,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $countId;
        });
    }
}
