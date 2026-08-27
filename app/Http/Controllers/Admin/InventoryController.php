<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\Models\StockItem;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Inventory\Services\InventoryWorkflowService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /** Lists stock by warehouse/product with low-stock filtering and operational summaries. */
    public function index(Request $request): View
    {
        $query = StockItem::query()->with(['warehouse', 'product', 'variant'])
            ->when($request->filled('q'), fn ($builder) => $builder->whereHas('product', fn ($product) => $product->where('name', 'like', '%'.$request->q.'%')->orWhere('sku', 'like', '%'.$request->q.'%')))
            ->when($request->boolean('low_stock'), fn ($builder) => $builder->whereRaw('(on_hand - reserved - damaged) <= reorder_point'))
            ->latest('id');

        return view('admin.inventory.index', [
            'stockItems' => $query->paginate(30)->withQueryString(),
            'warehouses' => DB::table('warehouses')->where('is_active', true)->orderBy('name')->get(),
            'movements' => DB::table('stock_movements')->latest('id')->limit(15)->get(),
            'transfers' => DB::table('stock_transfers')->latest()->limit(10)->get(),
            'counts' => DB::table('stock_counts')->latest()->limit(10)->get(),
        ]);
    }

    /** Applies a manual audited adjustment through the row-locking inventory service. */
    public function adjust(Request $request, StockItem $stockItem, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate(['delta' => ['required', 'integer', 'not_in:0'], 'reason' => ['required', 'string', 'max:300']]);
        $inventory->adjust($stockItem->id, (int) $data['delta'], $data['reason']);

        return back()->with('success', 'اصلاح موجودی ثبت شد.');
    }

    /** Executes an atomic warehouse-to-warehouse stock transfer. */
    public function transfer(Request $request, InventoryWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'different:to_warehouse_id', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id'],
            'stock_item_id' => ['required', 'exists:stock_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $stock = StockItem::query()->findOrFail($data['stock_item_id']);
        abort_unless($stock->warehouse_id === (int) $data['from_warehouse_id'], 422);
        $workflow->transfer((int) $data['from_warehouse_id'], (int) $data['to_warehouse_id'], [[
            'product_id' => $stock->product_id,
            'product_variant_id' => $stock->product_variant_id,
            'quantity' => (int) $data['quantity'],
        ]], $data['note'] ?? null);

        return back()->with('success', 'انتقال انبار ثبت شد.');
    }

    /** Reconciles one or more physical stock counts with the immutable ledger. */
    public function count(Request $request, InventoryWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['warehouse_id' => ['required', 'exists:warehouses,id'], 'counts' => ['required', 'array', 'min:1'], 'counts.*' => ['required', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:1000']]);
        $workflow->count((int) $data['warehouse_id'], $data['counts'], $data['note'] ?? null);

        return back()->with('success', 'انبارگردانی ثبت و مغایرت‌ها اصلاح شد.');
    }
}
