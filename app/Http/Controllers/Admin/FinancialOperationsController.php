<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\PaymentTransaction;
use App\Domain\Payment\Services\PaymentReconciliationService;
use App\Domain\Payment\Services\PaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FinancialOperationsController extends Controller
{
    /** Re-verifies a provider transaction server-to-server and stores a reconciliation audit row. */
    public function reconcile(PaymentTransaction $transaction, PaymentReconciliationService $reconciliation): RedirectResponse
    {
        abort_if(in_array($transaction->gateway, ['wallet', ...PaymentService::MANUAL_GATEWAYS], true), 422, 'این روش پرداخت Reconcile آنلاین ندارد.');
        try {
            $reconciliation->reconcile($transaction, $transaction->gateway === 'zarinpal' ? ['Status' => 'OK'] : []);
        } catch (\Throwable $exception) {
            return back()->withErrors(['reconcile' => 'Reconcile ناموفق بود: '.$exception->getMessage()]);
        }
        return back()->with('success', 'وضعیت تراکنش با Provider تطبیق داده شد.');
    }

    /** Requests a provider-side refund and persists its immutable audit payload. */
    public function refund(Request $request, PaymentTransaction $transaction, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1', 'max:'.$transaction->amount]]);
        if ($transaction->status !== 'verified' || in_array($transaction->gateway, ['wallet', ...PaymentService::MANUAL_GATEWAYS], true)) {
            throw new RuntimeException('این تراکنش برای Refund مستقیم معتبر نیست.');
        }
        $result = $payments->gateway($transaction->gateway)->refund($transaction, (int) $data['amount']);
        DB::table('payment_reconciliations')->insert([
            'payment_transaction_id' => $transaction->id,
            'status' => $result->successful ? 'refund_verified' : 'refund_failed',
            'provider_reference' => $result->referenceId,
            'payload' => json_encode(['amount' => (int) $data['amount'], 'provider' => $result->payload, 'message' => $result->message], JSON_UNESCAPED_UNICODE),
            'checked_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $result->successful ? back()->with('success', 'Refund توسط Provider تأیید شد.') : back()->withErrors(['refund' => $result->message ?: 'Refund توسط Provider رد شد.']);
    }
}
