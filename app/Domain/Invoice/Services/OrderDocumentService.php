<?php

namespace App\Domain\Invoice\Services;

use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\Shipment;
use App\Support\JalaliDate;
use Dompdf\Dompdf;

class OrderDocumentService
{
    public function __construct(private readonly JalaliDate $jalali) {}

    public function packingSlip(Order $order): string
    {
        return $this->pdf('documents.packing-slip', ['order' => $order->loadMissing(['items', 'shipments']), 'jalali' => $this->jalali], 'A4');
    }

    public function thermalReceipt(Order $order): string
    {
        return $this->pdf('documents.thermal-receipt', ['order' => $order->loadMissing('items'), 'jalali' => $this->jalali], [0, 0, 226.77, 700]);
    }

    public function shippingLabel(Shipment $shipment): string
    {
        return $this->pdf('documents.shipping-label', ['shipment' => $shipment->loadMissing('order.items'), 'order' => $shipment->order, 'jalali' => $this->jalali], [0, 0, 283.46, 425.20]);
    }

    private function pdf(string $view, array $data, string|array $paper): string
    {
        $dompdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->setPaper($paper, 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
