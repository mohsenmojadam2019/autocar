<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\Services\ProcurementService;
use App\Domain\Order\Models\Order;
use App\Domain\Returns\Services\ReturnService;
use App\Domain\Shipping\Services\FulfillmentService;
use App\Domain\Wholesale\Services\WholesaleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommerceOperationsController extends Controller
{
    /** Shows suppliers, purchase orders and warehouse-receiving context. */
    public function procurement(): View
    {
        return view('admin.commerce.procurement', [
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->where('is_active', true)->orderBy('name')->get(),
            'products' => DB::table('products')->whereNull('deleted_at')->orderBy('name')->limit(500)->get(['slug', 'name', 'sku']),
            'purchaseOrders' => DB::table('purchase_orders')->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')->join('warehouses', 'warehouses.id', '=', 'purchase_orders.warehouse_id')->select('purchase_orders.*', 'suppliers.name as supplier_name', 'warehouses.name as warehouse_name')->latest('purchase_orders.id')->paginate(30),
        ]);
    }

    /** Creates a supplier master record. */
    public function storeSupplier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'code' => ['required', 'string', 'max:60', 'unique:suppliers,code'],
            'contact_name' => ['nullable', 'string', 'max:190'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'contract_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        DB::table('suppliers')->insert($data + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'تأمین‌کننده ایجاد شد.');
    }

    /** Creates a purchase order from slug-addressed product rows. */
    public function storePurchaseOrder(Request $request, ProcurementService $procurement): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_slug' => ['required', 'string', 'exists:products,slug'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
        ]);
        $procurement->createPurchaseOrder((int) $data['supplier_id'], (int) $data['warehouse_id'], $data['items'], $data['expected_at'] ?? null, $data['notes'] ?? null);

        return back()->with('success', 'سفارش خرید ثبت شد.');
    }

    /** Receives selected purchase-order line quantities. */
    public function receivePurchaseOrder(Request $request, int $purchaseOrder, ProcurementService $procurement): RedirectResponse
    {
        $data = $request->validate(['items' => ['required', 'array'], 'items.*' => ['nullable', 'integer', 'min:0']]);
        $procurement->receive($purchaseOrder, $data['items']);

        return back()->with('success', 'رسید انبار ثبت شد.');
    }

    /** Shows shipping methods, zones, rates and active shipments. */
    public function shipping(): View
    {
        return view('admin.commerce.shipping', [
            'methods' => DB::table('shipping_methods')->orderBy('name')->get(),
            'zones' => DB::table('shipping_zones')->orderBy('name')->get(),
            'rates' => DB::table('shipping_rates')->join('shipping_methods', 'shipping_methods.id', '=', 'shipping_rates.shipping_method_id')->join('shipping_zones', 'shipping_zones.id', '=', 'shipping_rates.shipping_zone_id')->select('shipping_rates.*', 'shipping_methods.name as method_name', 'shipping_zones.name as zone_name')->latest('shipping_rates.id')->get(),
            'shipments' => DB::table('shipments')->join('orders', 'orders.id', '=', 'shipments.order_id')->leftJoin('shipping_methods', 'shipping_methods.id', '=', 'shipments.shipping_method_id')->select('shipments.*', 'orders.number as order_number', 'shipping_methods.name as method_name')->latest('shipments.id')->paginate(30),
            'orders' => Order::query()->whereIn('status', ['paid', 'reviewing', 'sourcing', 'ready_to_ship'])->latest()->limit(100)->get(['id', 'number', 'status']),
        ]);
    }

    /** Creates an enabled shipping method. */
    public function storeShippingMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'], 'code' => ['required', 'string', 'max:60', 'unique:shipping_methods,code'],
            'type' => ['required', Rule::in(['flat', 'weight', 'pickup', 'courier'])], 'base_price' => ['required', 'integer', 'min:0'],
            'price_per_kg' => ['nullable', 'integer', 'min:0'], 'free_over' => ['nullable', 'integer', 'min:0'],
            'min_days' => ['nullable', 'integer', 'min:0'], 'max_days' => ['nullable', 'integer', 'min:0'],
        ]);
        DB::table('shipping_methods')->insert($data + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'روش ارسال ایجاد شد.');
    }

    /** Creates a province/city shipping zone. */
    public function storeShippingZone(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:190'], 'provinces' => ['nullable', 'string'], 'cities' => ['nullable', 'string']]);
        DB::table('shipping_zones')->insert([
            'name' => $data['name'],
            'provinces' => json_encode($this->csv($data['provinces'] ?? ''), JSON_UNESCAPED_UNICODE),
            'cities' => json_encode($this->csv($data['cities'] ?? ''), JSON_UNESCAPED_UNICODE),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'منطقه ارسال ایجاد شد.');
    }

    /** Creates one weighted/order-value shipping rate. */
    public function storeShippingRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shipping_zone_id' => ['required', 'exists:shipping_zones,id'], 'shipping_method_id' => ['required', 'exists:shipping_methods,id'],
            'min_weight_grams' => ['nullable', 'integer', 'min:0'], 'max_weight_grams' => ['nullable', 'integer', 'min:0'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'], 'max_order_amount' => ['nullable', 'integer', 'min:0'], 'price' => ['required', 'integer', 'min:0'],
        ]);
        DB::table('shipping_rates')->insert($data + ['min_weight_grams' => $data['min_weight_grams'] ?? 0, 'min_order_amount' => $data['min_order_amount'] ?? 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'نرخ ارسال ایجاد شد.');
    }

    /** Creates a shipment for an eligible order. */
    public function storeShipment(Request $request, FulfillmentService $fulfillment): RedirectResponse
    {
        $data = $request->validate(['order_id' => ['required', 'exists:orders,id'], 'shipping_method_id' => ['nullable', 'exists:shipping_methods,id'], 'carrier' => ['nullable', 'string', 'max:100'], 'tracking_code' => ['nullable', 'string', 'max:190'], 'cost' => ['nullable', 'integer', 'min:0'], 'weight_grams' => ['nullable', 'integer', 'min:0']]);
        $fulfillment->createShipment(Order::query()->findOrFail($data['order_id']), $data['shipping_method_id'] ?? null, $data['carrier'] ?? null, $data['tracking_code'] ?? null, (int) ($data['cost'] ?? 0), $data['weight_grams'] ?? null);

        return back()->with('success', 'مرسوله ساخته شد.');
    }

    /** Updates shipment tracking/status and order state. */
    public function updateShipment(Request $request, int $shipment, FulfillmentService $fulfillment): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['preparing', 'shipped', 'in_transit', 'delivered', 'failed', 'returned'])], 'location' => ['nullable', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:1000'], 'tracking_code' => ['nullable', 'string', 'max:190']]);
        $fulfillment->updateStatus($shipment, $data['status'], $data['location'] ?? null, $data['description'] ?? null, $data['tracking_code'] ?? null);

        return back()->with('success', 'وضعیت مرسوله به‌روزرسانی شد.');
    }

    /** Shows complete RMA queues including refund amounts. */
    public function returns(): View
    {
        return view('admin.commerce.returns', [
            'returns' => DB::table('returns')->join('orders', 'orders.id', '=', 'returns.order_id')->leftJoin('users', 'users.id', '=', 'returns.user_id')->select('returns.*', 'orders.number as order_number', 'users.name as customer_name')->latest('returns.id')->paginate(30),
        ]);
    }

    /** Approves an RMA with line-level condition/restock decisions and refund amount. */
    public function approveReturn(Request $request, int $return, ReturnService $returns): RedirectResponse
    {
        $data = $request->validate(['approved_refund' => ['required', 'integer', 'min:0'], 'admin_note' => ['nullable', 'string', 'max:2000'], 'items' => ['nullable', 'array'], 'items.*.condition' => ['nullable', 'string', 'max:24'], 'items.*.restock' => ['nullable', 'boolean']]);
        $returns->approve($return, $data['items'] ?? [], (int) $data['approved_refund'], $data['admin_note'] ?? null);

        return back()->with('success', 'مرجوعی بررسی و بازپرداخت پردازش شد.');
    }

    /** Rejects an RMA. */
    public function rejectReturn(Request $request, int $return, ReturnService $returns): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['required', 'string', 'max:2000']]);
        $returns->reject($return, $data['admin_note']);

        return back()->with('success', 'مرجوعی رد شد.');
    }

    /** Shows B2B applications and quotations. */
    public function wholesale(): View
    {
        return view('admin.commerce.wholesale', [
            'accounts' => DB::table('wholesale_accounts')->join('users', 'users.id', '=', 'wholesale_accounts.user_id')->select('wholesale_accounts.*', 'users.name', 'users.mobile')->latest('wholesale_accounts.id')->paginate(30),
            'quotes' => DB::table('wholesale_quotes')->join('users', 'users.id', '=', 'wholesale_quotes.user_id')->select('wholesale_quotes.*', 'users.name')->latest('wholesale_quotes.id')->limit(50)->get(),
        ]);
    }

    /** Reviews a wholesale account. */
    public function reviewWholesale(Request $request, int $account, WholesaleService $wholesale): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'blocked'])], 'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'], 'credit_limit' => ['nullable', 'integer', 'min:0']]);
        $wholesale->reviewAccount($account, $data['status'], (int) ($data['discount_percent'] ?? 0), (int) ($data['credit_limit'] ?? 0));

        return back()->with('success', 'وضعیت حساب عمده ذخیره شد.');
    }

    /** Parses comma/newline-separated zone labels into a unique list. */
    private function csv(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\n\r]+/u', $value) ?: []))));
    }
}
