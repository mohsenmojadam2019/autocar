<?php

namespace App\Domain\Returns\Services;

use App\Domain\Inventory\Models\StockItem;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Payment\Services\WalletService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReturnService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PaymentService $payments,
        private readonly WalletService $wallets,
    ) {}

    /** Opens an RMA and prevents cumulative returns from exceeding purchased quantity. */
    public function request(Order $order, array $items, string $reason, ?int $userId = null): int
    {
        if (! in_array($order->status->value, ['shipped', 'delivered'], true)) {
            throw new RuntimeException('این سفارش در وضعیت قابل مرجوعی نیست.');
        }

        return DB::transaction(function () use ($order, $items, $reason, $userId): int {
            $id = DB::table('returns')->insertGetId([
                'order_id' => $order->id,
                'user_id' => $userId,
                'number' => 'RMA-'.now()->format('ymd').'-'.$order->id.'-'.random_int(100, 999),
                'status' => 'requested',
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($items as $orderItemId => $quantity) {
                $ordered = (int) $order->items()->whereKey($orderItemId)->value('quantity');
                $alreadyRequested = (int) DB::table('return_items')
                    ->join('returns', 'returns.id', '=', 'return_items.return_id')
                    ->where('returns.order_id', $order->id)
                    ->where('return_items.order_item_id', $orderItemId)
                    ->whereNotIn('returns.status', ['rejected', 'cancelled'])
                    ->sum('return_items.quantity');
                if (! $ordered || $quantity < 1 || $quantity + $alreadyRequested > $ordered) {
                    throw new RuntimeException('تعداد مرجوعی نامعتبر است.');
                }
                DB::table('return_items')->insert([
                    'return_id' => $id,
                    'order_item_id' => $orderItemId,
                    'quantity' => $quantity,
                    'restock' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $id;
        });
    }

    /** Approves selected RMA lines, optionally restocks them, and creates an auditable refund workflow. */
    public function approve(int $returnId, array $decisions, int $approvedRefund, ?string $note = null): void
    {
        DB::transaction(function () use ($returnId, $decisions, $approvedRefund, $note): void {
            $return = DB::table('returns')->where('id', $returnId)->lockForUpdate()->first();
            if (! $return || ! in_array($return->status, ['requested', 'reviewing'], true)) {
                throw new RuntimeException('درخواست مرجوعی قابل تأیید نیست.');
            }
            foreach ($decisions as $returnItemId => $decision) {
                $item = DB::table('return_items')->where('id', $returnItemId)->where('return_id', $returnId)->first();
                if (! $item) {
                    continue;
                }
                $restock = (bool) ($decision['restock'] ?? false);
                DB::table('return_items')->where('id', $item->id)->update([
                    'condition' => $decision['condition'] ?? 'accepted',
                    'restock' => $restock,
                    'updated_at' => now(),
                ]);
                if ($restock) {
                    $orderItem = DB::table('order_items')->where('id', $item->order_item_id)->first();
                    $stock = StockItem::query()
                        ->where('product_id', $orderItem->product_id)
                        ->when($orderItem->product_variant_id, fn ($query) => $query->where('product_variant_id', $orderItem->product_variant_id), fn ($query) => $query->whereNull('product_variant_id'))
                        ->first();
                    if ($stock) {
                        $this->inventory->adjust($stock->id, (int) $item->quantity, 'بازگشت موجودی RMA '.$return->number);
                    }
                }
            }
            DB::table('returns')->where('id', $returnId)->update([
                'status' => 'approved',
                'approved_refund' => max(0, $approvedRefund),
                'admin_note' => $note,
                'updated_at' => now(),
            ]);
        });

        if ($approvedRefund > 0) {
            $this->refund($returnId, $approvedRefund);
        }
    }

    /** Rejects an RMA without touching stock or payments. */
    public function reject(int $returnId, string $note): void
    {
        $updated = DB::table('returns')->where('id', $returnId)->whereIn('status', ['requested', 'reviewing'])->update([
            'status' => 'rejected',
            'admin_note' => $note,
            'updated_at' => now(),
        ]);
        if (! $updated) {
            throw new RuntimeException('درخواست مرجوعی قابل رد نیست.');
        }
    }

    /** Refunds wallet share first, then requests provider refund for the remaining amount. */
    private function refund(int $returnId, int $amount): void
    {
        $return = DB::table('returns')->where('id', $returnId)->first();
        $order = Order::query()->findOrFail($return->order_id);
        $remaining = $amount;
        $walletRefund = min($remaining, (int) $order->wallet_total);
        if ($walletRefund > 0 && $order->user_id) {
            $this->wallets->credit($order->user_id, $walletRefund, 'return', $returnId, 'بازپرداخت '.$return->number);
            $remaining -= $walletRefund;
        }

        $payment = PaymentTransaction::query()->where('order_id', $order->id)->where('status', 'verified')->whereNotIn('gateway', ['wallet'])->latest('id')->first();
        $status = 'completed';
        $reference = $walletRefund > 0 ? 'wallet' : null;
        $meta = ['wallet_refund' => $walletRefund];
        if ($remaining > 0) {
            if (! $payment || in_array($payment->gateway, PaymentService::MANUAL_GATEWAYS, true)) {
                $status = 'manual_required';
            } else {
                $result = $this->payments->gateway($payment->gateway)->refund($payment, $remaining);
                $status = $result->successful ? 'completed' : 'provider_failed';
                $reference = $result->referenceId;
                $meta['provider'] = $result->payload;
                $meta['message'] = $result->message;
            }
        }
        DB::table('refunds')->insert([
            'order_id' => $order->id,
            'return_id' => $returnId,
            'payment_transaction_id' => $payment?->id,
            'amount' => $amount,
            'status' => $status,
            'reference_id' => $reference,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($status === 'completed') {
            DB::table('returns')->where('id', $returnId)->update(['status' => 'refunded', 'updated_at' => now()]);
        }
    }
}
