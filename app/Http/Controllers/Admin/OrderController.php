<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /** Lists orders with server-side number/status/customer filters. */
    public function index(Request $request): View
    {
        $orders = Order::query()->when($request->filled('q'), fn ($q) => $q->where('number', 'like', '%'.$request->q.'%'))->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->paginate(30)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    /** Shows order snapshots, items and lifecycle history. */
    public function show(Order $order): View
    {
        return view('admin.orders.show', ['order' => $order->load(['items', 'statusHistory'])]);
    }

    /** Applies only a legal order-state transition through the domain state machine. */
    public function transition(Request $request, Order $order, OrderService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:1000']]);
        $next = OrderStatus::tryFrom($data['status']);
        abort_unless($next, 422);
        $service->transition($order, $next, $data['note'] ?? null);

        return back()->with('success','وضعیت سفارش تغییر کرد.');
    }
}
