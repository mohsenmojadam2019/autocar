<?php

namespace App\Domain\Invoice\Services;

use App\Domain\Order\Models\Order;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /** Creates or returns the immutable invoice snapshot for an order. */
    public function issue(Order $order,bool $official=false): object
    {
        $existing=DB::table('invoices')->where('order_id',$order->id)->where('is_official',$official)->first(); if($existing) return $existing;
        $order->loadMissing('items'); $number='INV-'.now()->format('ymd').'-'.$order->id;
        $id=DB::table('invoices')->insertGetId(['order_id'=>$order->id,'number'=>$number,'type'=>'sale','is_official'=>$official,'snapshot'=>json_encode(['order'=>$order->toArray()],JSON_UNESCAPED_UNICODE),'issued_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return DB::table('invoices')->find($id);
    }

    /** Renders an A4-ready PDF from the server-side Blade invoice template. */
    public function pdf(Order $order,bool $official=false): string
    {
        $invoice=$this->issue($order,$official); $html=view('documents.invoice',['order'=>$order->loadMissing('items'),'invoice'=>$invoice])->render();
        $dompdf=new Dompdf(['isRemoteEnabled'=>false,'defaultFont'=>'DejaVu Sans']); $dompdf->loadHtml($html,'UTF-8'); $dompdf->setPaper('A4','portrait'); $dompdf->render(); return $dompdf->output();
    }
}
