<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Services\AbandonedCartService;
use App\Domain\Customer\Models\CompareList;
use App\Domain\Customer\Models\Wishlist;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecoveryShareController extends Controller
{
    public function recover(Request $request, string $token, AbandonedCartService $service): RedirectResponse
    {
        if (! $request->user()) {
            $request->session()->put('url.intended', route('cart.recover',['token'=>$token]));
            return redirect()->route('login');
        }
        $cart = $service->recover($token, $request->user()->id);
        $request->session()->put('cart_token', $cart->token);
        return redirect()->route('cart.index')->with('success','سبد خرید شما بازیابی شد.');
    }

    public function wishlist(string $token): View
    {
        $list = Wishlist::query()->where('share_token',$token)->with(['products.media','products.brand'])->firstOrFail();
        return view('storefront.shared-products',['title'=>'لیست علاقه‌مندی اشتراکی','products'=>$list->products]);
    }

    public function compare(string $token): View
    {
        $list = CompareList::query()->where('share_token',$token)->with(['products.media','products.brand','products.attributeValues.attribute'])->firstOrFail();
        return view('storefront.compare',['list'=>$list,'shared'=>true]);
    }
}
