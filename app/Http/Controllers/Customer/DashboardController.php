<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Builds the customer account summary without exposing another customer's records. */ public function __invoke(Request $request): View { $orders=Order::query()->where('user_id',$request->user()->id)->latest()->limit(6)->get(); $wallet=DB::table('wallets')->where('user_id',$request->user()->id)->first(); return view('customer.dashboard',['orders'=>$orders,'wallet'=>$wallet,'vehicles'=>$request->user()->vehicles()->count()]); }
}
