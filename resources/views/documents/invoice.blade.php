<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body{font-family:"DejaVu Sans",sans-serif;direction:rtl;color:#111;font-size:11px}.wrap{border:1px solid #bbb;padding:14px}.head{display:flex;justify-content:space-between;border-bottom:2px solid #222;padding-bottom:10px;margin-bottom:12px}.box{border:1px solid #ddd;padding:9px;margin-bottom:10px}.grid{display:table;width:100%}.cell{display:table-cell;width:50%;vertical-align:top;padding:4px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px;text-align:right}th{background:#f3f3f3}.totals{width:45%;margin-right:auto;margin-top:12px}.totals td:first-child{font-weight:bold}.legal{background:#f8f9fa;padding:6px;margin-bottom:8px}.muted{color:#666;font-size:9px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <div><h2>{{ ($snapshot['invoice_kind'] ?? 'natural') === 'legal' ? 'فاکتور فروش حقوقی' : 'فاکتور فروش حقیقی' }}</h2><div>شماره: {{ $invoice->number }}</div></div>
        <div>تاریخ صدور: {{ $snapshot['issued_at_jalali'] ?? '' }}<br>سفارش: {{ $snapshot['order_number'] ?? $order->number }}</div>
    </div>
    @php($seller=$snapshot['seller'] ?? []) @php($buyer=$snapshot['buyer'] ?? [])
    <div class="grid">
        <div class="cell"><div class="box"><b>فروشنده</b><br>{{ $seller['name'] ?? 'AutoCar' }}<br>@if($seller['national_id'] ?? null)شناسه ملی: {{ $seller['national_id'] }}<br>@endif @if($seller['economic_code'] ?? null)کد اقتصادی: {{ $seller['economic_code'] }}<br>@endif {{ $seller['address'] ?? '' }} {{ $seller['phone'] ?? '' }}</div></div>
        <div class="cell"><div class="box"><b>خریدار</b><br>@if(($buyer['type'] ?? 'natural')==='legal'){{ $buyer['company_name'] ?? '' }}<br>شناسه ملی: {{ $buyer['national_id'] ?? '' }}<br>کد اقتصادی: {{ $buyer['economic_code'] ?? '' }}@else{{ $buyer['full_name'] ?? '' }}<br>کد ملی: {{ $buyer['national_code'] ?? '' }}@endif<br>{{ $buyer['province'] ?? '' }} {{ $buyer['city'] ?? '' }} {{ $buyer['address'] ?? '' }}<br>{{ $buyer['mobile'] ?? $buyer['phone'] ?? '' }}</div></div>
    </div>
    <table><thead><tr><th>#</th><th>کالا</th><th>SKU</th><th>تعداد</th><th>فی</th><th>تخفیف</th><th>مالیات</th><th>جمع</th></tr></thead><tbody>
    @foreach($snapshot['items'] ?? [] as $i=>$item)<tr><td>{{ $i+1 }}</td><td>{{ $item['name'] }}</td><td>{{ $item['sku'] }}</td><td>{{ number_format($item['quantity']) }}</td><td>{{ number_format($item['unit_price']) }}</td><td>{{ number_format($item['discount_total']) }}</td><td>{{ number_format($item['tax_total']) }}</td><td>{{ number_format($item['line_total']) }}</td></tr>@endforeach
    </tbody></table>
    @php($totals=$snapshot['totals'] ?? [])
    <table class="totals"><tr><td>جمع کالا</td><td>{{ number_format($totals['subtotal'] ?? 0) }}</td></tr><tr><td>تخفیف</td><td>{{ number_format($totals['discount_total'] ?? 0) }}</td></tr><tr><td>ارسال</td><td>{{ number_format($totals['shipping_total'] ?? 0) }}</td></tr><tr><td>مالیات</td><td>{{ number_format($totals['tax_total'] ?? 0) }}</td></tr><tr><td>مبلغ نهایی</td><td><b>{{ number_format($totals['grand_total'] ?? 0) }} ریال</b></td></tr></table>
    <p class="muted">اطلاعات هویتی این سند در زمان ثبت سفارش Snapshot شده و تغییر بعدی پروفایل مشتری روی این فاکتور اثر ندارد.</p>
</div>
</body></html>
