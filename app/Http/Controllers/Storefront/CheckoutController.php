<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Models\Cart;
use App\Domain\Checkout\Services\CheckoutService;
use App\Domain\Customer\Models\BillingProfile;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Promotion\Models\Coupon;
use App\Domain\Shipping\Services\ShippingRateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /** Displays checkout with customer invoice profiles, wallet and initial shipping rates. */
    public function index(Request $request, PaymentService $payments, ShippingRateService $shipping): View
    {
        $cart = $this->cartForUser($request)->load(['items.product', 'items.variant']);
        $defaultAddress = $request->user()->addresses()->first();
        $weight = (int) $cart->items->sum(fn ($item) => ((int) ($item->product->weight_grams ?? 0)) * (int) $item->quantity);
        $rates = $defaultAddress
            ? $shipping->rates($defaultAddress->province, $defaultAddress->city, $weight, $cart->subtotal())
            : collect();

        return view('storefront.checkout.index', [
            'cart' => $cart,
            'addresses' => $request->user()->addresses()->get(),
            'billingProfiles' => $request->user()->billingProfiles()->get(),
            'defaultAddress' => $defaultAddress,
            'shippingRates' => $rates,
            'gateways' => $payments->supportedGateways(),
            'wallet' => \App\Domain\Payment\Models\Wallet::query()->where('user_id', $request->user()->id)->first(),
        ]);
    }

    /** Returns authoritative shipping choices for the current cart and destination. */
    public function shippingRates(Request $request, ShippingRateService $shipping): JsonResponse
    {
        $data = $request->validate([
            'province' => ['required', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);
        $cart = $this->cartForUser($request)->load('items.product');
        $weight = (int) $cart->items->sum(fn ($item) => ((int) ($item->product->weight_grams ?? 0)) * (int) $item->quantity);
        $rates = $shipping->rates($data['province'], $data['city'] ?? null, $weight, $cart->subtotal());

        return response()->json(['data' => $rates->values()]);
    }

    /** Creates an immutable natural/legal order snapshot and initiates the remaining payment amount. */
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
            'shipping_method_id' => ['nullable', 'integer'],
            'billing_profile_id' => ['nullable', 'integer', 'exists:billing_profiles,id'],
            'invoice_kind' => ['required', Rule::in(['natural', 'legal'])],
            'use_wallet' => ['nullable', 'boolean'],
        ]);
        $cart = $this->cartForUser($request);
        $coupon = ! empty($data['coupon']) ? Coupon::query()->whereRaw('UPPER(code) = ?', [mb_strtoupper($data['coupon'])])->first() : null;
        $profile = ! empty($data['billing_profile_id'])
            ? BillingProfile::query()->whereKey($data['billing_profile_id'])->where('user_id', $request->user()->id)->firstOrFail()
            : null;
        $address = array_intersect_key($data, array_flip(['full_name', 'mobile', 'province', 'city', 'postal_code', 'address']));
        $order = $checkout->createOrder(
            $cart,
            $address,
            $coupon,
            $data['shipping_method_id'] ?? null,
            (bool) ($data['use_wallet'] ?? false),
            $profile,
            $data['invoice_kind'],
        );
        $payment = $payments->initiate($order, $data['gateway'], route('payment.callback', ['gateway' => $data['gateway']]));
        $resultUrl = $this->signedResultUrl($order);

        if ($payment['settled'] ?? false) {
            return redirect()->to($resultUrl)->with('success', 'سفارش با موفقیت تسویه شد.');
        }
        if ($payment['manual']) {
            return redirect()->to($resultUrl)->with('success', $data['gateway'] === 'card_to_card'
                ? 'سفارش ثبت شد؛ رسید کارت‌به‌کارت را از پنل سفارش بارگذاری کنید.'
                : 'سفارش پرداخت در محل ثبت شد.');
        }
        if (! $payment['redirect_url']) {
            return redirect()->to($resultUrl)->withErrors(['payment' => 'ایجاد تراکنش پرداخت ناموفق بود.']);
        }

        return redirect()->away($payment['redirect_url']);
    }

    /** Verifies online callbacks against stored authority and redirects through a short-lived signed result URL. */
    public function callback(Request $request, string $gateway, PaymentService $payments): RedirectResponse
    {
        abort_unless(in_array($gateway, PaymentService::ONLINE_GATEWAYS, true), 404);
        $authority = (string) ($request->input('Authority') ?? $request->input('authority') ?? $request->input('id') ?? '');
        $transaction = PaymentTransaction::query()->where('gateway', $gateway)->where('authority', $authority)->latest('id')->firstOrFail();
        $payments->verify($transaction, $request->all());

        return redirect()->to($this->signedResultUrl($transaction->order));
    }

    /** Shows payment state only to the order owner or holders of a valid temporary signature. */
    public function result(Request $request, string $order): View
    {
        $order = Order::query()->where('number', $order)->with(['items', 'payments'])->firstOrFail();
        $isOwner = $request->user() && (int) $order->user_id === (int) $request->user()->id;
        abort_unless($isOwner || URL::hasValidSignature($request), 403);

        return view('storefront.checkout.result', compact('order'));
    }

    /** Returns the current user's active cart and blocks cross-customer token reuse. */
    private function cartForUser(Request $request): Cart
    {
        return Cart::query()
            ->where('token', $request->session()->get('cart_token'))
            ->where('status', 'active')
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    /** Creates a short-lived signed result link suitable for payment-provider browser redirects. */
    private function signedResultUrl(Order $order): string
    {
        return URL::temporarySignedRoute('payment.result', now()->addHours(2), ['order' => $order->number]);
    }
}
