<?php

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;

class PaymentReconciliationService
{
    public function __construct(private readonly PaymentService $payments) {}

    /** Re-verifies a non-verified transaction and stores a durable reconciliation audit row. */
    public function reconcile(PaymentTransaction $transaction, array $providerPayload = []): PaymentTransaction
    {
        $result = $transaction->status === 'verified' ? $transaction : $this->payments->verify($transaction, $providerPayload);
        DB::table('payment_reconciliations')->insert([
            'payment_transaction_id' => $transaction->id,
            'status' => $result->status,
            'provider_reference' => $result->reference_id,
            'payload' => json_encode($providerPayload, JSON_UNESCAPED_UNICODE),
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $result;
    }
}
