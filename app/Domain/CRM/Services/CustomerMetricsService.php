<?php

namespace App\Domain\CRM\Services;

use Illuminate\Support\Facades\DB;

class CustomerMetricsService
{
    /** Rebuilds 360-degree customer order count and lifetime value from non-cancelled orders. */
    public function rebuild(int $userId): void
    {
        $metrics=DB::table('orders')->where('user_id',$userId)->whereNotIn('status',['cancelled'])->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(grand_total),0) as lifetime_value')->first();
        DB::table('customer_profiles')->updateOrInsert(['user_id'=>$userId],['orders_count'=>(int)$metrics->orders_count,'lifetime_value'=>(int)$metrics->lifetime_value,'updated_at'=>now(),'created_at'=>now()]);
    }
}
