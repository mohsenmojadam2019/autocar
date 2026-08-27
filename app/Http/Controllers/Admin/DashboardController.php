<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reports\Services\ReportService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): View
    {
        return view('admin.dashboard', [
            'kpis' => $reports->dashboard(30),
            'series' => $reports->dailySales(30),
            'orders' => DB::table('orders')->latest()->limit(8)->get(),
            'lowStock' => DB::table('stock_items')->join('products', 'products.id', '=', 'stock_items.product_id')->whereRaw('(stock_items.on_hand-stock_items.reserved-stock_items.damaged)<=stock_items.reorder_point')->select('products.name', 'stock_items.*')->limit(8)->get(),
            'orderStatuses' => DB::table('orders')->selectRaw('status, COUNT(*) total')->groupBy('status')->orderByDesc('total')->get(),
            'providerHealth' => DB::table('provider_health_checks')->latest('checked_at')->limit(12)->get(),
            'queue' => ['pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0, 'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0],
        ]);
    }
}
