<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Invoice\Services\OrderDocumentService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderService;
use App\Domain\Order\Services\PhoneOrderService;
use App\Domain\Shipping\Models\Shipment;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrderCompletionController extends Controller
{
    public function kanban(): View
    {
        $orders = Order::query()->with('user')->latest()->limit(500)->get()->groupBy(fn (Order $order) => $order->status->value);

        return view('admin.orders.kanban', compact('orders'));
    }

    public function phone(Request $request, PhoneOrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'regex:/^09\d{9}$/'], 'full_name' => ['required', 'string', 'max:120'], 'province' => ['required', 'string', 'max:80'], 'city' => ['required', 'string', 'max:80'], 'address' => ['required', 'string', 'max:1000'], 'postal_code' => ['nullable', 'string', 'max:20'],
            'invoice_kind' => ['required', 'in:natural,legal'], 'shipping_total' => ['nullable', 'integer', 'min:0'], 'discount_total' => ['nullable', 'integer', 'min:0'], 'customer_note' => ['nullable', 'string', 'max:2000'], 'internal_note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_slug' => ['required', 'string', 'exists:products,slug'], 'items.*.variant_sku' => ['nullable', 'string', 'max:100'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'company_name' => ['nullable', 'required_if:invoice_kind,legal', 'string', 'max:190'], 'national_id' => ['nullable', 'string', 'max:30'], 'economic_code' => ['nullable', 'string', 'max:30'], 'registration_number' => ['nullable', 'string', 'max:50'],
        ]);
        $user = User::query()->where('mobile', $data['mobile'])->first();
        $address = array_intersect_key($data, array_flip(['full_name', 'mobile', 'province', 'city', 'postal_code', 'address']));
        $billing = $data['invoice_kind'] === 'legal' ? array_merge($address, array_intersect_key($data, array_flip(['company_name', 'national_id', 'economic_code', 'registration_number']))) : $address;
        $order = $orders->create($user?->id, $address, $data['items'], [
            'invoice_kind' => $data['invoice_kind'], 'billing_profile_snapshot' => $billing, 'shipping_total' => $data['shipping_total'] ?? 0, 'discount_total' => $data['discount_total'] ?? 0, 'customer_note' => $data['customer_note'] ?? null, 'internal_note' => $data['internal_note'] ?? null,
        ]);

        return redirect()->route('admin.orders.show', $order)->with('success', 'سفارش تلفنی ثبت و موجودی رزرو شد.');
    }

    public function bulk(Request $request, OrderService $service): RedirectResponse
    {
        $data = $request->validate(['orders' => ['required', 'array', 'min:1', 'max:100'], 'orders.*' => ['integer', 'exists:orders,id'], 'status' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:1000']]);
        $next = OrderStatus::tryFrom($data['status']);
        abort_unless($next, 422);
        DB::transaction(function () use ($data, $next, $service): void {
            foreach (Order::query()->whereKey($data['orders'])->lockForUpdate()->get() as $order) {
                if ($order->status->canTransitionTo($next)) {
                    $service->transition($order, $next, $data['note'] ?? 'تغییر گروهی وضعیت');
                }
            }
        });

        return back()->with('success', 'عملیات گروهی سفارش‌ها انجام شد.');
    }

    public function packing(Order $order, OrderDocumentService $documents): Response
    {
        return response($documents->packingSlip($order), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="packing-'.$order->number.'.pdf"']);
    }

    public function thermal(Order $order, OrderDocumentService $documents): Response
    {
        return response($documents->thermalReceipt($order), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="receipt-'.$order->number.'.pdf"']);
    }

    public function label(Shipment $shipment, OrderDocumentService $documents): Response
    {
        return response($documents->shippingLabel($shipment), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="label-'.$shipment->id.'.pdf"']);
    }
}
