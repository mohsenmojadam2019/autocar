<?php

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    /** Returns or creates a customer's wallet without changing its balance. */
    public function forUser(int $userId): Wallet
    {
        return Wallet::query()->firstOrCreate(['user_id' => $userId], ['balance' => 0]);
    }

    /** Debits up to the requested amount under a wallet row lock and returns the amount actually used. */
    public function debit(int $userId, int $requestedAmount, string $referenceType, int $referenceId, string $description = 'پرداخت از کیف پول'): int
    {
        if ($requestedAmount <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($userId, $requestedAmount, $referenceType, $referenceId, $description): int {
            $wallet = $this->forUser($userId);
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $existing = $wallet->entries()
                ->where('type', 'debit')
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->first();
            if ($existing) {
                return abs((int) $existing->amount);
            }

            $amount = min(max(0, (int) $wallet->balance), $requestedAmount);
            if ($amount <= 0) {
                return 0;
            }

            $wallet->balance -= $amount;
            if ($wallet->balance < 0) {
                throw new RuntimeException('موجودی کیف پول کافی نیست.');
            }
            $wallet->save();
            $wallet->entries()->create([
                'type' => 'debit',
                'amount' => -$amount,
                'balance_after' => $wallet->balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'meta' => ['requested_amount' => $requestedAmount],
                'created_at' => now(),
            ]);

            return $amount;
        });
    }

    /** Credits a wallet idempotently for refunds, adjustments or promotional balances. */
    public function credit(int $userId, int $amount, string $referenceType, int $referenceId, string $description): int
    {
        if ($amount <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($userId, $amount, $referenceType, $referenceId, $description): int {
            $wallet = $this->forUser($userId);
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $existing = $wallet->entries()
                ->where('type', 'credit')
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->first();
            if ($existing) {
                return (int) $existing->amount;
            }

            $wallet->balance += $amount;
            $wallet->save();
            $wallet->entries()->create([
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_at' => now(),
            ]);

            return $amount;
        });
    }
}
