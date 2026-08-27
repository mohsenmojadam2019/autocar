<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Models\Cart;
use App\Domain\Checkout\Services\CheckoutService;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Promotion\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /** Displays checkout for the authenticated user's active non-empty cart. */
    public function index(Request $request): View
    {
        $cart=Cart::query()->where('token',$request->session()->get('cart_token'))->where('status','active')->with(['items.product','items.variant'])->firstOrFail();
        return view('storefront.checkout.index',['cart'=>$cart,'addresses'=>$request->user()->addresses??collect()]);
    }

    /** Creates the immutable order snapshot then redirects to the selected payment provider. */
    public function store(Request $request, CheckoutService $checkout, PaymentService $payments): RedirectResponse
    {
        $data=$request->validate(['full_name'=>['required','string','max:120'],'mobile'=>['required','regex:/^09\d{9}$/'],'province'=>['required','string','max:80'],'city'=>['required','string','max:80'],'postal_code'=>['nullable','string','max:20'],'address'=>['required','string','max:1000'],'coupon'=>['nullable','string','max:40'],'gateway'=>['required','in:zarinpal,idpay,zibal,nextpay,payir']]);
        $cart=Cart::query()->where('token',$request->session()->get('cart_token'))->where('status','active')->where('user_id',$request->user()->id)->firstOrFail();
        $coupon=!empty($data['coupon'])?Coupon::query()->where('code',$data['coupon'])->first():null;
        $address=array_intersect_key($data,array_flip(['full_name','mobile','province','city','postal_code','address']));
        $order=$checkout->createOrder($cart,$address,$coupon);
        $payment=$payments->initiate($order,$data['gateway'],route('payment.callback',['gateway'=>$data['gateway']]));
        if(!$payment['redirect_url']) return redirect()->route('payment.result',['order'=>$order->number])->withErrors(['payment'=>'ایجاد تراکنش پرداخت ناموفق بود.']);
        return redirect()->away($payment['redirect_url']);
    }

    /** Verifies provider callback against the stored transaction before showing the final result page. */
    public function callback(Request $request,string $gateway,PaymentService $payments): RedirectResponse
    {
        $authority=(string)($request->input('Authority')??$request->input('authority')??$request->input('id')??'');
        $transaction=PaymentTransaction::query()->where('gateway',$gateway)->where('authority',$authority)->latest('id')->firstOrFail();
        $payments->verify($transaction,$request->all());
        return redirect()->route('payment.result',['order'=>$transaction->order->number]);
    }

    /** Shows payment/order state without mutating it. */
    public function result(string $order): View
    {
        $order=\App\Domain\Order\Models\Order::query()->where('number',$order)->with('items')->firstOrFail(); return view('storefront.checkout.result',compact('order'));
    }
}
