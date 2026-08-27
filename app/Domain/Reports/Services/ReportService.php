<?php

namespace App\Domain\Reports\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function dashboard(int $days = 30): array
    {
        return $this->summary(now()->subDays(max(1, $days) - 1)->startOfDay(), now()->endOfDay())['kpis'];
    }

    public function dailySales(int $days = 30): array
    {
        return $this->dailySalesBetween(now()->subDays(max(1, $days) - 1)->startOfDay(), now()->endOfDay());
    }

    /** Produces finance, tax, inventory, campaign, returns and merchandising metrics for a bounded period. */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $orders = DB::table('orders')->whereBetween('created_at', [$from, $to])->whereNotIn('status', ['cancelled']);
        $sales = (int) (clone $orders)->sum('grand_total');
        $orderCount = (int) (clone $orders)->count();
        $discount = (int) (clone $orders)->sum('discount_total');
        $tax = (int) (clone $orders)->sum('tax_total');
        $shipping = (int) (clone $orders)->sum('shipping_total');
        $itemEconomics = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(order_items.line_total),0) revenue, COALESCE(SUM(order_items.quantity * COALESCE(product_variants.purchase_price, products.purchase_price, 0)),0) cost')->first();
        $cost = (int) ($itemEconomics->cost ?? 0);
        $itemRevenue = (int) ($itemEconomics->revenue ?? 0);
        $grossProfit = $itemRevenue - $cost - $discount;
        $days = max(1, $from->diffInDays($to) + 1);
        $previousTo = $from->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();
        $previousSales = (int) DB::table('orders')->whereBetween('created_at', [$previousFrom, $previousTo])->whereNotIn('status', ['cancelled'])->sum('grand_total');
        $growth = $previousSales > 0 ? round((($sales - $previousSales) / $previousSales) * 100, 2) : ($sales > 0 ? 100.0 : 0.0);

        return [
            'from' => $from, 'to' => $to,
            'kpis' => [
                'sales' => $sales, 'orders' => $orderCount, 'average_order' => $orderCount ? (int) round($sales / $orderCount) : 0,
                'discount' => $discount, 'tax' => $tax, 'shipping' => $shipping, 'cost' => $cost, 'gross_profit' => $grossProfit,
                'sales_growth_percent' => $growth,
                'customers' => (int) DB::table('users')->whereBetween('created_at', [$from, $to])->count(),
                'low_stock' => (int) DB::table('stock_items')->whereRaw('(on_hand - reserved - damaged) <= reorder_point')->count(),
                'pending_returns' => (int) DB::table('returns')->whereIn('status', ['requested', 'reviewing'])->count(),
                'approved_refunds' => (int) DB::table('returns')->whereBetween('updated_at', [$from, $to])->sum('approved_refund'),
            ],
            'series' => $this->dailySalesBetween($from, $to),
            'ordersByStatus' => DB::table('orders')->whereBetween('created_at', [$from, $to])->selectRaw('status, COUNT(*) total')->groupBy('status')->orderByDesc('total')->get(),
            'topProducts' => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'cancelled')->selectRaw('order_items.name, order_items.sku, SUM(order_items.quantity) qty, SUM(order_items.line_total) revenue')->groupBy('order_items.name', 'order_items.sku')->orderByDesc('revenue')->limit(20)->get(),
            'topBrands' => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->leftJoin('products', 'products.id', '=', 'order_items.product_id')->leftJoin('brands', 'brands.id', '=', 'products.brand_id')->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'cancelled')->selectRaw("COALESCE(brands.name,'بدون برند') brand, SUM(order_items.quantity) qty, SUM(order_items.line_total) revenue")->groupBy('brands.name')->orderByDesc('revenue')->limit(15)->get(),
            'inventory' => DB::table('stock_items')->leftJoin('products', 'products.id', '=', 'stock_items.product_id')->selectRaw('COALESCE(SUM(stock_items.on_hand),0) on_hand, COALESCE(SUM(stock_items.reserved),0) reserved, COALESCE(SUM(stock_items.damaged),0) damaged, COALESCE(SUM(stock_items.on_hand * COALESCE(products.purchase_price,0)),0) inventory_value')->first(),
            'campaign' => DB::table('sms_campaign_recipients')->whereBetween('created_at', [$from, $to])->selectRaw("COUNT(*) total, SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) sent, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) failed")->first(),
        ];
    }

    public function dailySalesBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('orders')->whereBetween('created_at', [$from, $to])->whereNotIn('status', ['cancelled'])->selectRaw('DATE(created_at) as day, SUM(grand_total) as total, COUNT(*) as orders')->groupByRaw('DATE(created_at)')->orderBy('day')->get()->map(fn ($row) => ['day' => $row->day, 'total' => (int) $row->total, 'orders' => (int) $row->orders])->all();
    }

    public function salesRows(CarbonInterface $from, CarbonInterface $to): iterable
    {
        return DB::table('orders')->select('number', 'source', 'invoice_kind', 'status', 'subtotal', 'discount_total', 'shipping_total', 'tax_total', 'grand_total', 'created_at')->whereBetween('created_at', [$from, $to])->orderByDesc('id')->cursor();
    }

    public function csv(iterable $rows, array $headers): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, (array) $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$csv;
    }
}
