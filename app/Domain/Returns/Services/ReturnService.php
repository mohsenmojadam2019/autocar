<?php

namespace App\Domain\Returns\Services;

use App\Domain\Order\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReturnService
{
    /** Opens an RMA for delivered/shipped orders and validates requested quantities against the order. */
    public function request(Order $order,array $items,string $reason,?int $userId=null): int
    {
        if(!in_array($order->status->value,['shipped','delivered'],true)) throw new RuntimeException('این سفارش در وضعیت قابل مرجوعی نیست.');
        return DB::transaction(function() use($order,$items,$reason,$userId){ $id=DB::table('returns')->insertGetId(['order_id'=>$order->id,'user_id'=>$userId,'number'=>'RMA-'.now()->format('ymd').'-'.$order->id.'-'.random_int(100,999),'status'=>'requested','reason'=>$reason,'created_at'=>now(),'updated_at'=>now()]); foreach($items as $orderItemId=>$qty){ $ordered=$order->items()->whereKey($orderItemId)->value('quantity'); if(!$ordered || $qty<1 || $qty>$ordered) throw new RuntimeException('تعداد مرجوعی نامعتبر است.'); DB::table('return_items')->insert(['return_id'=>$id,'order_item_id'=>$orderItemId,'quantity'=>$qty,'restock'=>false,'created_at'=>now(),'updated_at'=>now()]); } return $id; });
    }
}
