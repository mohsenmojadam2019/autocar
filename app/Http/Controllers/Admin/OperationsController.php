<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reports\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Support\JalaliDate;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    public function payments(Request $request): View
    {
        $rows = DB::table('payment_transactions')->when($request->filled('gateway'), fn ($query) => $query->where('gateway', $request->gateway))->latest()->paginate(30)->withQueryString();

        return view('admin.operations.table', ['title' => 'پرداخت‌ها', 'description' => 'تراکنش، Authority، Reference و وضعیت Verify', 'rows' => $rows, 'columns' => ['id' => '#', 'gateway' => 'درگاه', 'status' => 'وضعیت', 'authority' => 'Authority', 'reference_id' => 'Reference', 'amount' => 'مبلغ', 'created_at' => 'تاریخ']]);
    }

    public function returns(): View
    {
        $rows = DB::table('returns')->latest()->paginate(30);

        return view('admin.operations.table', ['title' => 'مرجوعی و بازپرداخت', 'description' => 'RMAهای جزئی/کامل و مبالغ درخواستی', 'rows' => $rows, 'columns' => ['number' => 'شماره RMA', 'order_id' => 'سفارش', 'status' => 'وضعیت', 'reason_code' => 'دلیل', 'requested_refund' => 'درخواست بازپرداخت', 'approved_refund' => 'تأییدشده', 'created_at' => 'تاریخ']]);
    }

    public function content(): View
    {
        $rows = DB::table('posts')->latest()->paginate(30);

        return view('admin.operations.table', ['title' => 'محتوا و بلاگ', 'description' => 'مقالات منتشرشده و پیش‌نویس‌های SEO', 'rows' => $rows, 'columns' => ['id' => '#', 'title' => 'عنوان', 'slug' => 'Slug', 'status' => 'وضعیت', 'published_at' => 'انتشار', 'updated_at' => 'آخرین تغییر']]);
    }

    public function reports(Request $request, ReportService $reports): View
    {
        [$from, $to] = $this->range($request);

        return view('admin.reports.index', ['report' => $reports->summary($from, $to), 'from' => $from, 'to' => $to]);
    }

    public function salesCsv(Request $request, ReportService $reports): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        return response()->streamDownload(function () use ($reports, $from, $to): void {
            echo $reports->csv($reports->salesRows($from, $to), ['number', 'source', 'invoice_kind', 'status', 'subtotal', 'discount', 'shipping', 'tax', 'grand_total', 'created_at']);
        }, 'autocar-sales-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Streams SpreadsheetML that opens natively in Excel without a heavyweight spreadsheet dependency. */
    public function salesExcel(Request $request, ReportService $reports, JalaliDate $jalali): Response
    {
        [$from, $to] = $this->range($request);
        $rows = collect($reports->salesRows($from, $to))->map(function ($row) use ($jalali): array {
            $values = (array) $row;
            $values['created_at'] = $jalali->format(Carbon::parse($row->created_at));

            return $values;
        });
        $headers = ['number', 'source', 'invoice_kind', 'status', 'subtotal', 'discount_total', 'shipping_total', 'tax_total', 'grand_total', 'created_at'];
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Sales"><Table>';
        $xml .= '<Row>'.collect($headers)->map(fn ($header) => '<Cell><Data ss:Type="String">'.e($header).'</Data></Cell>')->implode('').'</Row>';
        foreach ($rows as $row) {
            $xml .= '<Row>'.collect($headers)->map(fn ($key) => '<Cell><Data ss:Type="String">'.e((string) ($row[$key] ?? '')).'</Data></Cell>')->implode('').'</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';

        return response($xml, 200, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="autocar-report.xls"']);
    }

    public function reportPdf(Request $request, ReportService $reports): Response
    {
        [$from, $to] = $this->range($request);
        $report = $reports->summary($from, $to);
        $dompdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml(view('documents.management-report', compact('report'))->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="autocar-management-report.pdf"']);
    }

    private function range(Request $request): array
    {
        $data = $request->validate(['from_date' => ['nullable', 'date'], 'to_date' => ['nullable', 'date', 'after_or_equal:from_date']]);
        $from = isset($data['from_date']) ? Carbon::parse($data['from_date'])->startOfDay() : now()->subDays(29)->startOfDay();
        $to = isset($data['to_date']) ? Carbon::parse($data['to_date'])->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }
}
