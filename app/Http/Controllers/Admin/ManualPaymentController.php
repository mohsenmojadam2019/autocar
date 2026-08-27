<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Services\ManualPaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ManualPaymentController extends Controller
{
    /** Lists pending and reviewed manual card-to-card proofs. */
    public function index(): View
    {
        $proofs = DB::table('manual_payment_proofs')->join('payment_transactions', 'payment_transactions.id', '=', 'manual_payment_proofs.payment_transaction_id')->leftJoin('orders', 'orders.id', '=', 'payment_transactions.order_id')->select('manual_payment_proofs.*', 'payment_transactions.gateway', 'orders.number as order_number')->latest('manual_payment_proofs.id')->paginate(30);

        return view('admin.payments.manual', compact('proofs'));
    }

    /** Approves one pending proof with the authenticated administrator. */
    public function approve(Request $request, int $proof, ManualPaymentService $manual): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $manual->approve($proof, $request->user()->id, $data['note'] ?? null);

        return back()->with('success', 'رسید تأیید و سفارش پرداخت‌شده شد.');
    }

    /** Rejects one pending proof without paying the order. */
    public function reject(Request $request, int $proof, ManualPaymentService $manual): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $manual->reject($proof, $request->user()->id, $data['note'] ?? null);

        return back()->with('success', 'رسید رد شد.');
    }
}
