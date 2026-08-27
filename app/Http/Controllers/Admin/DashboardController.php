<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reports\Services\ReportService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Renders operational KPIs, daily sales, recent orders and low-stock alerts. */
    public function __invoke(ReportService $reports): View
    {
        return view('admin.dashboard', ['kpis' => $reports->dashboard(30), 'series' => $reports->dailySales(30), 'orders' => DB::table('orders')->latest()->limit(8)->get(), 'lowStock' => DB::table('stock_items')->join('products', 'products.id', '=', 'stock_items.product_id')->whereRaw('(stock_items.on_hand-stock_items.reserved-stock_items.damaged)<=stock_items.reorder_point')->select('products.name', 'stock_items.*')->limit(8)->get()]);
    }
}
