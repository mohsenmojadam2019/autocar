<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingController extends Controller
{
    /** Shows tracking form or an order only after matching its business number and mobile snapshot. */
    public function __invoke(Request $request): View
    {
        $order = null;
        if ($request->filled('number') || $request->filled('mobile')) {
            $data = $request->validate(['number' => ['required', 'string', 'max:40'], 'mobile' => ['required', 'regex:/^09\d{9}$/']]);
            $order = Order::query()->where('number', $data['number'])->with(['statusHistory', 'shipments'])->get()->first(function (Order $candidate) use ($data): bool {
                return data_get($candidate->shipping_address, 'mobile') === $data['mobile'] || data_get($candidate->billing_address, 'mobile') === $data['mobile'];
            });
        }

        return view('storefront.tracking', compact('order'));
    }
}
