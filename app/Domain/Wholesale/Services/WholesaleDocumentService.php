<?php

namespace App\Domain\Wholesale\Services;

use App\Services\Settings\SettingsRepository;
use App\Support\JalaliDate;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WholesaleDocumentService
{
    public function __construct(private readonly SettingsRepository $settings, private readonly JalaliDate $jalali) {}

    /** Renders a Jalali wholesale proforma from persisted quote lines. */
    public function proforma(int $quoteId): string
    {
        $quote = DB::table('wholesale_quotes')->join('users', 'users.id', '=', 'wholesale_quotes.user_id')->where('wholesale_quotes.id', $quoteId)->select('wholesale_quotes.*', 'users.name', 'users.mobile', 'users.legal_name', 'users.national_id', 'users.economic_code')->first();
        if (! $quote) {
            throw new RuntimeException('پیش‌فاکتور یافت نشد.');
        }
        $items = DB::table('wholesale_quote_items')->join('products', 'products.id', '=', 'wholesale_quote_items.product_id')->where('wholesale_quote_items.wholesale_quote_id', $quoteId)->select('wholesale_quote_items.*', 'products.name', 'products.sku')->get();
        $html = view('documents.wholesale-proforma', [
            'quote' => $quote,
            'items' => $items,
            'jalali' => $this->jalali,
            'seller' => [
                'name' => $this->settings->get('invoice.seller_name', 'AutoCar'),
                'national_id' => $this->settings->get('invoice.seller_national_id'),
                'economic_code' => $this->settings->get('invoice.seller_economic_code'),
                'address' => $this->settings->get('invoice.seller_address'),
            ],
        ])->render();
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
