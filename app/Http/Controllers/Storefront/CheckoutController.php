<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Models\Cart;
use App\Domain\Checkout\Services\CheckoutService;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Promotion\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /** Displays checkout for the authenticated user's active non-empty cart. */
    public function index(Request $request, PaymentService $payments): View
    {
        $cart = Cart::query()->where('token', $request->session()->get('cart_token'))->where('status', 'active')->with(['items.product', 'items.variant'])->firstOrFail();

        return view('storefront.checkout.index', [
            'cart' => $cart,
            'addresses' => $request->user()->addresses ?? collect(),
            'gateways' => $payments->supportedGateways(),
        ]);
    }

    /** Creates an immutable order snapshot then redirects online payments or opens manual-payment flow. */
    public function store(Request $request, CheckoutService $checkout, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'regex:/^09\d{9}$/'],
            'province' => ['required', 'string', 'max:80'],
            'city' => ['required', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
            'coupon' => ['nullable', 'string', 'max:40'],
            'gateway' => ['required', Rule::in($payments->supportedGateways())],
        ]);
        $cart = Cart::query()->where('token', $request->session()->get('cart_token'))->where('status', 'active')->where('user_id', $request->user()->id)->firstOrFail();
        $coupon = ! empty($data['coupon']) ? Coupon::query()->where('code', $data['coupon'])->first() : null;
        $address = array_intersect_key($data, array_flip(['full_name', 'mobile', 'province', 'city', 'postal_code', 'address']));
        $order = $checkout->createOrder($cart, $address, $coupon);
        $payment = $payments->initiate($order, $data['gateway'], route('payment.callback', ['gateway' => $data['gateway']]));

        if ($payment['manual']) {
            return redirect()->route('payment.result', ['order' => $order->number])->with('success', $data['gateway'] === 'card_to_card' ? 'سفارش ثبت شد؛ رسید کارت‌به‌کارت را از پنل سفارش بارگذاری کنید.' : 'سفارش پرداخت در محل ثبت شد.');
        }
        if (! $payment['redirect_url']) {
            return redirect()->route('payment.result', ['order' => $order->number])->withErrors(['payment' => 'ایجاد تراکنش پرداخت ناموفق بود.']);
        }

        return redirect()->away($payment['redirect_url']);
    }

    /** Verifies online provider callbacks only against the stored authority and gateway. */
    public function callback(Request $request, string $gateway, PaymentService $payments): RedirectResponse
    {
        abort_unless(in_array($gateway, PaymentService::ONLINE_GATEWAYS, true), 404);
        $authority = (string) ($request->input('Authority') ?? $request->input('authority') ?? $request->input('id') ?? '');
        $transaction = PaymentTransaction::query()->where('gateway', $gateway)->where('authority', $authority)->latest('id')->firstOrFail();
        $payments->verify($transaction, $request->all());

        return redirect()->route('payment.result', ['order' => $transaction->order->number]);
    }

    /** Shows payment/order state without mutating it. */
    public function result(string $order): View
    {
        $order = Order::query()->where('number', $order)->with(['items', 'payments'])->firstOrFail();

        return view('storefront.checkout.result', compact('order'));
    }
}
