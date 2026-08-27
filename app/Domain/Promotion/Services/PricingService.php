<?php

namespace App\Domain\Promotion\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Promotion\Models\AutomaticPromotion;
use Illuminate\Database\Eloquent\Builder;

class PricingService
{
    /** Returns the authoritative price snapshot after the best currently applicable automatic promotion. */
    public function price(Product $product, ?ProductVariant $variant = null, int $quantity = 1): array
    {
        $basePrice = (int) ($variant?->sale_price ?? $product->sale_price);
        $compareAt = (int) ($variant?->compare_at_price ?? $product->compare_at_price ?? 0);
        $categoryIds = $product->relationLoaded('categories')
            ? $product->categories->pluck('id')->all()
            : $product->categories()->pluck('categories.id')->all();

        $promotions = AutomaticPromotion::query()
            ->running()
            ->where('minimum_quantity', '<=', max(1, $quantity))
            ->where(fn (Builder $query) => $query
                ->whereNull('maximum_quantity')
                ->orWhere('maximum_quantity', '>=', max(1, $quantity)))
            ->where(function (Builder $scope) use ($product, $categoryIds): void {
                $scope->whereHas('products', fn (Builder $query) => $query->whereKey($product->id));

                if ($categoryIds !== []) {
                    $scope->orWhereHas('categories', fn (Builder $query) => $query->whereKey($categoryIds));
                }

                if ($product->brand_id) {
                    $scope->orWhereHas('brands', fn (Builder $query) => $query->whereKey($product->brand_id));
                }

                $scope->orWhere(fn (Builder $global) => $global
                    ->doesntHave('products')
                    ->doesntHave('categories')
                    ->doesntHave('brands'));
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $winner = null;
        $finalPrice = $basePrice;
        foreach ($promotions as $promotion) {
            $candidate = $this->applyDiscount($basePrice, $promotion);
            if ($candidate < $finalPrice) {
                $finalPrice = $candidate;
                $winner = $promotion;
            }
        }

        $discountAmount = max(0, $basePrice - $finalPrice);
        $discountPercent = $basePrice > 0
            ? (int) round(($discountAmount / $basePrice) * 100)
            : 0;

        return [
            'base_price' => $basePrice,
            'final_price' => $finalPrice,
            'compare_at_price' => $winner ? max($compareAt, $basePrice) : $compareAt,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'promotion_id' => $winner?->id,
            'promotion_name' => $winner?->name,
            'badge_text' => $winner?->badge_text ?: ($winner ? $discountPercent.'٪ تخفیف' : null),
            'starts_at' => $winner?->starts_at,
            'ends_at' => $winner?->ends_at,
        ];
    }

    /** Applies one promotion rule to a base unit price while respecting its maximum-discount cap. */
    private function applyDiscount(int $basePrice, AutomaticPromotion $promotion): int
    {
        $value = (float) $promotion->discount_value;
        $discount = match ($promotion->discount_type) {
            'percentage' => (int) round($basePrice * ($value / 100)),
            'fixed' => (int) round($value),
            'sale_price' => max(0, $basePrice - (int) round($value)),
            default => 0,
        };

        if ($promotion->max_discount !== null) {
            $discount = min($discount, (int) $promotion->max_discount);
        }

        return max(0, $basePrice - max(0, $discount));
    }
}
