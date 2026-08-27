<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcurementService
{
    /** Creates a purchase order using product slugs as the external catalog reference. */
    public function createPurchaseOrder(int $supplierId, int $warehouseId, array $items, ?string $expectedAt = null, ?string $notes = null): int
    {
        if ($items === []) {
            throw new RuntimeException('حداقل یک قلم برای سفارش خرید لازم است.');
        }

        return DB::transaction(function () use ($supplierId, $warehouseId, $items, $expectedAt, $notes): int {
            $normalized = [];
            $subtotal = 0;
            foreach ($items as $item) {
                $product = Product::query()->where('slug', $item['product_slug'])->firstOrFail();
                $quantity = max(1, (int) $item['quantity']);
                $unitCost = max(0, (int) $item['unit_cost']);
                $lineTotal = $quantity * $unitCost;
                $subtotal += $lineTotal;
                $normalized[] = compact('product', 'quantity', 'unitCost', 'lineTotal') + ['variant_id' => $item['product_variant_id'] ?? null];
            }

            $id = DB::table('purchase_orders')->insertGetId([
                'number' => $this->nextNumber(),
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'status' => 'ordered',
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'total' => $subtotal,
                'expected_at' => $expectedAt,
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($normalized as $row) {
                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $id,
                    'product_id' => $row['product']->id,
                    'product_variant_id' => $row['variant_id'],
                    'quantity' => $row['quantity'],
                    'received_quantity' => 0,
                    'unit_cost' => $row['unitCost'],
                    'total' => $row['lineTotal'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $id;
        });
    }

    /** Receives PO quantities into warehouse stock, writes stock ledger and updates purchase cost/history. */
    public function receive(int $purchaseOrderId, array $receivedItems): void
    {
        DB::transaction(function () use ($purchaseOrderId, $receivedItems): void {
            $order = DB::table('purchase_orders')->where('id', $purchaseOrderId)->lockForUpdate()->first();
            if (! $order || in_array($order->status, ['cancelled', 'received'], true)) {
                throw new RuntimeException('سفارش خرید قابل دریافت نیست.');
            }

            foreach ($receivedItems as $itemId => $quantity) {
                $quantity = (int) $quantity;
                if ($quantity <= 0) {
                    continue;
                }
                $item = DB::table('purchase_order_items')->where('id', $itemId)->where('purchase_order_id', $order->id)->lockForUpdate()->first();
                if (! $item || $quantity > ((int) $item->quantity - (int) $item->received_quantity)) {
                    throw new RuntimeException('تعداد رسید یکی از اقلام بیشتر از مانده سفارش خرید است.');
                }

                $stockId = $this->stockItemId((int) $order->warehouse_id, (int) $item->product_id, $item->product_variant_id ? (int) $item->product_variant_id : null);
                $stock = DB::table('stock_items')->where('id', $stockId)->lockForUpdate()->first();
                $newBalance = (int) $stock->on_hand + $quantity;
                DB::table('stock_items')->where('id', $stockId)->update(['on_hand' => $newBalance, 'updated_at' => now()]);
                DB::table('stock_movements')->insert([
                    'stock_item_id' => $stockId,
                    'user_id' => auth()->id(),
                    'type' => 'purchase_receipt',
                    'quantity' => $quantity,
                    'balance_after' => $newBalance,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $order->id,
                    'reason' => 'رسید سفارش خرید '.$order->number,
                    'meta' => json_encode(['purchase_order_item_id' => $item->id], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ]);
                DB::table('purchase_order_items')->where('id', $item->id)->update([
                    'received_quantity' => (int) $item->received_quantity + $quantity,
                    'updated_at' => now(),
                ]);
                DB::table('products')->where('id', $item->product_id)->update(['purchase_price' => $item->unit_cost, 'updated_at' => now()]);
                DB::table('price_histories')->insert([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'user_id' => auth()->id(),
                    'purchase_price' => $item->unit_cost,
                    'sale_price' => DB::table('products')->where('id', $item->product_id)->value('sale_price') ?: 0,
                    'wholesale_price' => DB::table('products')->where('id', $item->product_id)->value('wholesale_price'),
                    'created_at' => now(),
                ]);
            }

            $open = DB::table('purchase_order_items')->where('purchase_order_id', $order->id)->whereColumn('received_quantity', '<', 'quantity')->exists();
            DB::table('purchase_orders')->where('id', $order->id)->update([
                'status' => $open ? 'partially_received' : 'received',
                'updated_at' => now(),
            ]);
        });
    }

    /** Cancels an open PO without mutating any already received stock. */
    public function cancel(int $purchaseOrderId): void
    {
        $order = DB::table('purchase_orders')->where('id', $purchaseOrderId)->first();
        if (! $order || in_array($order->status, ['received', 'cancelled'], true)) {
            throw new RuntimeException('سفارش خرید قابل لغو نیست.');
        }
        DB::table('purchase_orders')->where('id', $purchaseOrderId)->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    /** Finds or creates the warehouse stock row for a product/variant. */
    private function stockItemId(int $warehouseId, int $productId, ?int $variantId): int
    {
        $query = DB::table('stock_items')->where('warehouse_id', $warehouseId)->where('product_id', $productId);
        $variantId === null ? $query->whereNull('product_variant_id') : $query->where('product_variant_id', $variantId);
        if ($id = $query->value('id')) {
            return (int) $id;
        }

        return (int) DB::table('stock_items')->insertGetId([
            'warehouse_id' => $warehouseId,
            'warehouse_bin_id' => null,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'on_hand' => 0,
            'reserved' => 0,
            'damaged' => 0,
            'reorder_point' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Generates a human-readable purchasing number. */
    private function nextNumber(): string
    {
        do {
            $number = 'PO-'.now()->format('ymd').'-'.random_int(10000, 99999);
        } while (DB::table('purchase_orders')->where('number', $number)->exists());

        return $number;
    }
}
