<?php

namespace App\Domain\Invoice\Services;

use App\Domain\Order\Models\Order;
use App\Services\Settings\SettingsRepository;
use App\Support\JalaliDate;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly JalaliDate $jalali,
    ) {}

    /** Creates or returns the immutable natural/legal invoice snapshot for an order. */
    public function issue(Order $order, ?bool $official = null): object
    {
        $invoiceKind = $order->invoice_kind ?: 'natural';
        $official ??= $invoiceKind === 'legal';
        $existing = DB::table('invoices')
            ->where('order_id', $order->id)
            ->where('invoice_kind', $invoiceKind)
            ->where('is_official', $official)
            ->first();
        if ($existing) {
            return $existing;
        }

        $order->loadMissing('items');
        $datePart = str_replace('/', '', $this->jalali->date(now(), false));
        $number = 'INV-'.$datePart.'-'.$order->id;
        $buyer = $order->billing_profile_snapshot ?: $order->billing_address ?: [];
        $seller = $this->sellerSnapshot();
        $snapshot = [
            'order_number' => $order->number,
            'invoice_kind' => $invoiceKind,
            'issued_at_jalali' => $this->jalali->format(now()),
            'buyer' => $buyer,
            'seller' => $seller,
            'items' => $order->items->map(fn ($item) => [
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_total' => $item->discount_total,
                'tax_total' => $item->tax_total,
                'line_total' => $item->line_total,
            ])->all(),
            'totals' => [
                'subtotal' => $order->subtotal,
                'discount_total' => $order->discount_total,
                'wallet_total' => $order->wallet_total,
                'shipping_total' => $order->shipping_total,
                'tax_total' => $order->tax_total,
                'grand_total' => $order->grand_total,
                'currency' => $order->currency,
            ],
        ];

        $id = DB::table('invoices')->insertGetId([
            'order_id' => $order->id,
            'number' => $number,
            'type' => 'sale',
            'invoice_kind' => $invoiceKind,
            'is_official' => $official,
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'buyer_snapshot' => json_encode($buyer, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'seller_snapshot' => json_encode($seller, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('invoices')->find($id);
    }

    /** Renders an A4 invoice whose identity and totals come only from immutable snapshots. */
    public function pdf(Order $order, ?bool $official = null): string
    {
        $invoice = $this->issue($order, $official);
        $html = view('documents.invoice', [
            'order' => $order->loadMissing('items'),
            'invoice' => $invoice,
            'snapshot' => json_decode((string) $invoice->snapshot, true, flags: JSON_THROW_ON_ERROR),
        ])->render();
        $dompdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /** Builds seller identity from centrally managed invoice settings. */
    private function sellerSnapshot(): array
    {
        return [
            'name' => $this->settings->get('invoice.seller_name', 'AutoCar'),
            'national_id' => $this->settings->get('invoice.seller_national_id'),
            'economic_code' => $this->settings->get('invoice.seller_economic_code'),
            'registration_number' => $this->settings->get('invoice.seller_registration_number'),
            'phone' => $this->settings->get('invoice.seller_phone'),
            'postal_code' => $this->settings->get('invoice.seller_postal_code'),
            'address' => $this->settings->get('invoice.seller_address'),
        ];
    }
}
