<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reports\Services\ReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    /** Lists payment transactions and gateway verification state. */
    public function payments(Request $request): View
    {
        $rows = DB::table('payment_transactions')->when($request->filled('gateway'), fn ($query) => $query->where('gateway', $request->gateway))->latest()->paginate(30)->withQueryString();
        return view('admin.operations.table', ['title' => 'پرداخت‌ها', 'description' => 'تراکنش، Authority، Reference و وضعیت Verify', 'rows' => $rows, 'columns' => ['id' => '#', 'gateway' => 'درگاه', 'status' => 'وضعیت', 'authority' => 'Authority', 'reference_id' => 'Reference', 'amount' => 'مبلغ', 'created_at' => 'تاریخ']]);
    }

    /** Lists returns/refunds for finance and after-sales operations. */
    public function returns(): View
    {
        $rows = DB::table('returns')->latest()->paginate(30);
        return view('admin.operations.table', ['title' => 'مرجوعی و بازپرداخت', 'description' => 'RMAهای جزئی/کامل و مبالغ درخواستی', 'rows' => $rows, 'columns' => ['number' => 'شماره RMA', 'order_id' => 'سفارش', 'status' => 'وضعیت', 'reason_code' => 'دلیل', 'requested_refund' => 'درخواست بازپرداخت', 'approved_refund' => 'تأییدشده', 'created_at' => 'تاریخ']]);
    }

    /** Lists content records for editorial review. */
    public function content(): View
    {
        $rows = DB::table('posts')->latest()->paginate(30);
        return view('admin.operations.table', ['title' => 'محتوا و بلاگ', 'description' => 'مقالات منتشرشده و پیش‌نویس‌های SEO', 'rows' => $rows, 'columns' => ['id' => '#', 'title' => 'عنوان', 'slug' => 'Slug', 'status' => 'وضعیت', 'published_at' => 'انتشار', 'updated_at' => 'آخرین تغییر']]);
    }

    /** Renders detailed business reports and current dashboard series. */
    public function reports(ReportService $reports): View
    {
        return view('admin.reports.index', ['kpis' => $reports->dashboard(30), 'series' => $reports->dailySales(90), 'topProducts' => DB::table('order_items')->selectRaw('name, sku, SUM(quantity) as qty, SUM(line_total) as revenue')->groupBy('name', 'sku')->orderByDesc('revenue')->limit(20)->get()]);
    }

    /** Streams a UTF-8 BOM CSV sales report without loading all rows into PHP memory. */
    public function salesCsv(ReportService $reports): StreamedResponse
    {
        return response()->streamDownload(function () use ($reports): void {
            $rows = DB::table('orders')->select('number', 'status', 'subtotal', 'discount_total', 'shipping_total', 'tax_total', 'grand_total', 'created_at')->orderByDesc('id')->cursor();
            echo $reports->csv($rows, ['number', 'status', 'subtotal', 'discount', 'shipping', 'tax', 'grand_total', 'created_at']);
        }, 'autocar-sales-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
