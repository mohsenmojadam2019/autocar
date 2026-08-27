<?php

namespace App\Domain\Reports\Services;

use Illuminate\Support\Facades\DB;

class ReportService
{
    /** Returns dashboard KPIs using server-side aggregates suitable for the admin shell. */
    public function dashboard(int $days=30): array
    {
        $from=now()->subDays(max(1,$days))->startOfDay();
        $orders=DB::table('orders')->where('created_at','>=',$from)->whereNotIn('status',['cancelled']);
        return ['sales'=>(int)(clone $orders)->sum('grand_total'),'orders'=>(int)(clone $orders)->count(),'average_order'=>(int)round((clone $orders)->avg('grand_total')??0),'customers'=>(int)DB::table('users')->where('created_at','>=',$from)->count(),'low_stock'=>(int)DB::table('stock_items')->whereRaw('(on_hand - reserved - damaged) <= reorder_point')->count(),'pending_returns'=>(int)DB::table('returns')->whereIn('status',['requested','reviewing'])->count()];
    }

    /** Returns daily sales series for the requested range without loading order rows into PHP memory. */
    public function dailySales(int $days=30): array
    {
        return DB::table('orders')->where('created_at','>=',now()->subDays($days))->whereNotIn('status',['cancelled'])->selectRaw('DATE(created_at) as day, SUM(grand_total) as total, COUNT(*) as orders')->groupByRaw('DATE(created_at)')->orderBy('day')->get()->map(fn($r)=>['day'=>$r->day,'total'=>(int)$r->total,'orders'=>(int)$r->orders])->all();
    }

    /** Streams a query result as RFC-compatible CSV to avoid spreadsheet-package coupling. */
    public function csv(iterable $rows,array $headers): string
    {
        $stream=fopen('php://temp','r+'); fputcsv($stream,$headers); foreach($rows as $row) fputcsv($stream,(array)$row); rewind($stream); $csv=stream_get_contents($stream); fclose($stream); return "\xEF\xBB\xBF".$csv;
    }
}
