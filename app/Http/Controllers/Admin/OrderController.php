<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderService;
use App\Http\Controllers\Controller;
use App\Support\AdminTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /** Lists orders through the shared bounded server-side table pipeline. */
    public function index(Request $request, AdminTable $table): View
    {
        $query = $table->apply(
            $request,
            Order::query(),
            ['number'],
            ['status' => 'status', 'source' => 'source'],
            ['id', 'number', 'status', 'source', 'grand_total', 'created_at', 'updated_at'],
            'id',
        );

        return view('admin.orders.index', ['orders' => $query->paginate($table->perPage($request))->withQueryString()]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', ['order' => $order->load(['items', 'statusHistory'])]);
    }

    public function transition(Request $request, Order $order, OrderService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:1000']]);
        $next = OrderStatus::tryFrom($data['status']);
        abort_unless($next, 422);
        $service->transition($order, $next, $data['note'] ?? null);

        return back()->with('success', 'وضعیت سفارش تغییر کرد.');
    }
}
