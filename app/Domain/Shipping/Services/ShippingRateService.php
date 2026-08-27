<?php

namespace App\Domain\Shipping\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShippingRateService
{
    /** Resolves enabled shipping methods/rates for destination, weight and order amount. */
    public function rates(string $province, ?string $city, int $weightGrams, int $orderAmount): Collection
    {
        $zones = DB::table('shipping_zones')->where('is_active', true)->get()->filter(function ($zone) use ($province, $city): bool {
            $provinces = json_decode($zone->provinces ?: '[]', true) ?: [];
            $cities = json_decode($zone->cities ?: '[]', true) ?: [];
            return ($provinces === [] || in_array($province, $provinces, true)) && ($cities === [] || $city === null || in_array($city, $cities, true));
        });
        if ($zones->isEmpty()) {
            return collect();
        }

        return DB::table('shipping_rates')->join('shipping_methods', 'shipping_methods.id', '=', 'shipping_rates.shipping_method_id')
            ->whereIn('shipping_zone_id', $zones->pluck('id'))->where('shipping_rates.is_active', true)->where('shipping_methods.is_active', true)
            ->where('min_weight_grams', '<=', $weightGrams)->where(fn ($q) => $q->whereNull('max_weight_grams')->orWhere('max_weight_grams', '>=', $weightGrams))
            ->where('min_order_amount', '<=', $orderAmount)->where(fn ($q) => $q->whereNull('max_order_amount')->orWhere('max_order_amount', '>=', $orderAmount))
            ->select(['shipping_methods.id', 'shipping_methods.name', 'shipping_methods.code', 'shipping_rates.price'])->orderBy('shipping_rates.price')->get();
    }
}
