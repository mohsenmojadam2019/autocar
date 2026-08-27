<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Customer\Models\Address;
use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Domain\Payment\Services\ManualPaymentService;
use App\Domain\Returns\Services\ReturnService;
use App\Domain\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    /** Lists all orders owned by the authenticated customer. */
    public function orders(Request $request): View
    {
        $orders = Order::query()->where('user_id', $request->user()->id)->with('shipments')->latest()->paginate(20);
        return view('customer.orders.index', compact('orders'));
    }

    /** Shows one owned order with payment, shipment, invoice and return timelines. */
    public function order(Request $request, string $number): View
    {
        $order = $this->ownedOrder($request, $number)->load(['items', 'payments', 'shipments', 'invoices', 'returns.items']);
        return view('customer.orders.show', compact('order'));
    }

    /** Streams the customer's own immutable A4 invoice PDF. */
    public function invoice(Request $request, string $number, InvoiceService $invoices): Response
    {
        $order = $this->ownedOrder($request, $number);
        return response($invoices->pdf($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-'.$order->number.'.pdf"',
        ]);
    }

    /** Shows wallet balance and immutable ledger entries. */
    public function wallet(Request $request): View
    {
        $wallet = DB::table('wallets')->where('user_id', $request->user()->id)->first();
        $entries = $wallet ? DB::table('wallet_entries')->where('wallet_id', $wallet->id)->latest('id')->paginate(30) : collect();
        return view('customer.wallet', compact('wallet', 'entries'));
    }

    /** Shows all customer-owned delivery addresses. */
    public function addresses(Request $request): View
    {
        return view('customer.addresses', ['addresses' => $request->user()->addresses()->get()]);
    }

    /** Creates one normalized address and optionally makes it the customer's default. */
    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:80'],
            'full_name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'regex:/^09\d{9}$/'],
            'province' => ['required', 'string', 'max:80'],
            'city' => ['required', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        if ($data['is_default'] ?? false) {
            Address::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
        }
        $request->user()->addresses()->create($data);
        return back()->with('success', 'آدرس ذخیره شد.');
    }

    /** Deletes only an address owned by the current customer. */
    public function destroyAddress(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->delete();
        return back()->with('success', 'آدرس حذف شد.');
    }

    /** Shows database notifications and channel preferences. */
    public function notifications(Request $request): View
    {
        $notifications = DB::table('notifications')->where('notifiable_type', $request->user()::class)->where('notifiable_id', $request->user()->id)->latest()->paginate(30);
        $preferences = DB::table('notification_preferences')->where('user_id', $request->user()->id)->get()->keyBy('event');
        return view('customer.notifications', compact('notifications', 'preferences'));
    }

    /** Updates per-event notification channels without accepting arbitrary columns. */
    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string', 'max:64'],
            'sms' => ['nullable', 'boolean'],
            'email' => ['nullable', 'boolean'],
            'database' => ['nullable', 'boolean'],
            'marketing' => ['nullable', 'boolean'],
        ]);
        DB::table('notification_preferences')->updateOrInsert(
            ['user_id' => $request->user()->id, 'event' => $data['event']],
            [
                'sms' => $data['sms'] ?? false,
                'email' => $data['email'] ?? false,
                'database' => $data['database'] ?? false,
                'marketing' => $data['marketing'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        return back()->with('success', 'تنظیم اعلان ذخیره شد.');
    }

    /** Lists the authenticated customer's RMA requests. */
    public function returns(Request $request): View
    {
        $returns = DB::table('returns')->where('user_id', $request->user()->id)->latest()->paginate(30);
        return view('customer.returns', compact('returns'));
    }

    /** Opens an RMA for the selected owned order and quantities. */
    public function requestReturn(Request $request, string $number, ReturnService $returns): RedirectResponse
    {
        $order = $this->ownedOrder($request, $number)->load('items');
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1'], 'items.*' => ['nullable', 'integer', 'min:0']]);
        $items = array_filter($data['items'], fn ($quantity) => (int) $quantity > 0);
        if ($items === []) {
            return back()->withErrors(['items' => 'حداقل یک قلم برای مرجوعی انتخاب کنید.']);
        }
        $returns->request($order, $items, $data['reason'], $request->user()->id);
        return back()->with('success', 'درخواست مرجوعی ثبت شد.');
    }

    /** Lists support tickets owned by the customer. */
    public function tickets(Request $request): View
    {
        $tickets = DB::table('tickets')->where('user_id', $request->user()->id)->latest()->paginate(30);
        return view('customer.tickets.index', compact('tickets'));
    }

    /** Opens a customer support ticket with an SLA deadline. */
    public function storeTicket(Request $request, TicketService $tickets): RedirectResponse
    {
        $data = $request->validate(['subject' => ['required', 'string', 'max:190'], 'message' => ['required', 'string', 'max:5000'], 'department' => ['required', 'in:support,sales,returns,parts'], 'priority' => ['required', 'in:low,normal,high,urgent']]);
        $tickets->open($request->user()->id, $data['subject'], $data['message'], $data['department'], $data['priority']);
        return back()->with('success', 'تیکت ثبت شد.');
    }

    /** Shows one owned ticket and its non-internal conversation. */
    public function ticket(Request $request, string $number): View
    {
        $ticket = DB::table('tickets')->where('number', $number)->where('user_id', $request->user()->id)->first();
        abort_unless($ticket, 404);
        $messages = DB::table('ticket_messages')->where('ticket_id', $ticket->id)->where('is_internal', false)->orderBy('id')->get();
        return view('customer.tickets.show', compact('ticket', 'messages'));
    }

    /** Appends a customer reply to an owned open ticket. */
    public function replyTicket(Request $request, string $number, TicketService $tickets): RedirectResponse
    {
        $ticket = DB::table('tickets')->where('number', $number)->where('user_id', $request->user()->id)->first();
        abort_unless($ticket, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $tickets->reply($ticket->id, $request->user()->id, $data['body']);
        return back()->with('success', 'پاسخ ثبت شد.');
    }

    /** Stores proof for the authenticated customer's pending card-to-card transaction. */
    public function submitManualPayment(Request $request, int $transaction, ManualPaymentService $manual): RedirectResponse
    {
        $payment = PaymentTransaction::query()->where('id', $transaction)->where('user_id', $request->user()->id)->firstOrFail();
        $data = $request->validate([
            'reference_code' => ['nullable', 'string', 'max:100'],
            'card_last4' => ['nullable', 'digits:4'],
            'paid_at' => ['nullable', 'date'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $manual->submit($payment, $data, $request->file('receipt'));
        return back()->with('success', 'رسید برای بررسی ثبت شد.');
    }

    /** Resolves an order by business number and current customer ownership. */
    private function ownedOrder(Request $request, string $number): Order
    {
        return Order::query()->where('number', $number)->where('user_id', $request->user()->id)->firstOrFail();
    }
}
