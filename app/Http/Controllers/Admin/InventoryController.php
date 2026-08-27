<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\Models\StockItem;
use App\Domain\Inventory\Services\InventoryService;
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
            'movements' => DB::table('stock_movements')->latest('id')->limit(10)->get(),
        ]);
    }

    /** Applies a manual audited adjustment through the row-locking inventory service. */
    public function adjust(Request $request, StockItem $stockItem, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate(['delta' => ['required', 'integer', 'not_in:0'], 'reason' => ['required', 'string', 'max:300']]);
        $inventory->adjust($stockItem->id, (int) $data['delta'], $data['reason']);
        return back()->with('success', 'اصلاح موجودی ثبت شد.');
    }
}
