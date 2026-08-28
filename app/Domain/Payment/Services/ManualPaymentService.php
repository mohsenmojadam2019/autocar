<?php

namespace App\Domain\Payment\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Services\OrderService;
use App\Domain\Payment\Models\PaymentTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ManualPaymentService
{
    public function __construct(private readonly OrderService $orders) {}

    /** Stores card-to-card evidence on local storage and leaves the order unpaid until reviewed. */
    public function submit(PaymentTransaction $transaction, array $data, ?UploadedFile $receipt = null): int
    {
        if ($transaction->gateway !== 'card_to_card' || $transaction->status === 'verified') {
            throw new RuntimeException('این تراکنش امکان ثبت رسید دستی ندارد.');
        }
        $path = $receipt?->store('manual-payments/'.now()->format('Y/m'), 'local');

        return (int) DB::table('manual_payment_proofs')->insertGetId([
            'payment_transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'reference_code' => $data['reference_code'] ?? null,
            'card_last4' => $data['card_last4'] ?? null,
            'amount' => $transaction->amount,
            'paid_at' => $data['paid_at'] ?? now(),
            'receipt_path' => $path,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Approves one proof exactly once and transitions the related order to paid. */
    public function approve(int $proofId, int $adminId, ?string $note = null): void
    {
        DB::transaction(function () use ($proofId, $adminId, $note): void {
            $proof = DB::table('manual_payment_proofs')->lockForUpdate()->where('id', $proofId)->first();
            if (! $proof || $proof->status !== 'pending') {
                throw new RuntimeException('رسید قابل تأیید نیست.');
            }

            /** @var PaymentTransaction $transaction */
            $transaction = PaymentTransaction::query()
                ->whereKey($proof->payment_transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status !== 'verified') {
                $transaction->update([
                    'status' => 'verified',
                    'reference_id' => $proof->reference_code ?: 'manual-'.$proof->id,
                    'verified_at' => now(),
                    'verify_payload' => ['manual_proof_id' => $proof->id, 'reviewed_by' => $adminId],
                ]);
                if ($transaction->order?->status === OrderStatus::PendingPayment) {
                    $this->orders->transition($transaction->order, OrderStatus::Paid, 'رسید کارت‌به‌کارت تأیید شد.');
                }
            }
            DB::table('manual_payment_proofs')->where('id', $proofId)->update([
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'review_note' => $note,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** Rejects a pending proof without mutating the order payment state. */
    public function reject(int $proofId, int $adminId, ?string $note = null): void
    {
        $updated = DB::table('manual_payment_proofs')->where('id', $proofId)->where('status', 'pending')->update([
            'status' => 'rejected',
            'reviewed_by' => $adminId,
            'review_note' => $note,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);
        if ($updated !== 1) {
            throw new RuntimeException('رسید قابل رد کردن نیست.');
        }
    }
}
