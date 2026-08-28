<?php

namespace App\Domain\Promotion\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Promotion\Models\AutomaticPromotion;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PricingService
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** Returns authoritative retail/B2B pricing and chooses the best permitted active price. */
    public function price(Product $product, ?ProductVariant $variant = null, int $quantity = 1, ?int $userId = null, bool $resolveAuthenticatedUser = true): array
    {
        if ($userId === null && $resolveAuthenticatedUser) {
            $userId = auth()->id();
        }
        $basePrice = (int) ($variant?->sale_price ?? $product->sale_price);
        $compareAt = (int) ($variant?->compare_at_price ?? $product->compare_at_price ?? 0);
        $categoryIds = $product->relationLoaded('categories') ? $product->categories->pluck('id')->all() : $product->categories()->pluck('categories.id')->all();
        $promotions = AutomaticPromotion::query()
            ->running()->where('minimum_quantity', '<=', max(1, $quantity))
            ->where(fn (Builder $query) => $query->whereNull('maximum_quantity')->orWhere('maximum_quantity', '>=', max(1, $quantity)))
            ->where(function (Builder $scope) use ($product, $categoryIds): void {
                $scope->whereHas('products', fn (Builder $query) => $query->whereKey($product->id));
                if ($categoryIds !== []) {
                    $scope->orWhereHas('categories', fn (Builder $query) => $query->whereKey($categoryIds));
                }
                if ($product->brand_id) {
                    $scope->orWhereHas('brands', fn (Builder $query) => $query->whereKey($product->brand_id));
                }
                $scope->orWhere(fn (Builder $global) => $global->doesntHave('products')->doesntHave('categories')->doesntHave('brands'));
            })->orderByDesc('priority')->orderBy('id')->get();

        $winner = null;
        $finalPrice = $basePrice;
        $tier = 'retail';
        foreach ($promotions as $promotion) {
            $candidate = $this->applyDiscount($basePrice, $promotion);
            if ($candidate < $finalPrice) {
                $finalPrice = $candidate;
                $winner = $promotion;
                $tier = 'promotion';
            }
        }

        $wholesale = $userId ? DB::table('wholesale_accounts')->where('user_id', $userId)->where('status', 'approved')->first() : null;
        if ($wholesale) {
            $contractBase = (int) ($variant?->wholesale_price ?? $product->wholesale_price ?? $basePrice);
            $candidate = max(0, (int) round($contractBase * (1 - ((int) $wholesale->discount_percent / 100))));
            if ($candidate < $finalPrice) {
                $finalPrice = $candidate;
                $winner = null;
                $tier = 'wholesale';
            }
        }

        $finalPrice = $this->roundPrice($finalPrice);
        $discountAmount = max(0, $basePrice - $finalPrice);
        $discountPercent = $basePrice > 0 ? (int) round(($discountAmount / $basePrice) * 100) : 0;

        return [
            'base_price' => $basePrice,
            'final_price' => $finalPrice,
            'compare_at_price' => $finalPrice < $basePrice ? max($compareAt, $basePrice) : $compareAt,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'promotion_id' => $winner?->id,
            'promotion_name' => $tier === 'wholesale' ? 'قیمت همکاری B2B' : $winner?->name,
            'badge_text' => $tier === 'wholesale' ? 'قیمت همکاری' : ($winner?->badge_text ?: ($winner ? $discountPercent.'٪ تخفیف' : null)),
            'starts_at' => $winner?->starts_at,
            'ends_at' => $winner?->ends_at,
            'pricing_tier' => $tier,
            'wholesale_account_id' => $wholesale?->id,
            'rounding_step' => max(1, (int) $this->settings->get('pricing.rounding_step', 1)),
        ];
    }

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

    /** Applies centrally configured Iranian price rounding after all discount/B2B rules. */
    private function roundPrice(int $price): int
    {
        $step = max(1, (int) $this->settings->get('pricing.rounding_step', 1));
        if ($step === 1 || $price === 0) {
            return max(0, $price);
        }

        $ratio = $price / $step;
        $rounded = match ((string) $this->settings->get('pricing.rounding_mode', 'nearest')) {
            'up' => (int) ceil($ratio) * $step,
            'down' => (int) floor($ratio) * $step,
            default => (int) round($ratio) * $step,
        };

        return max(0, $rounded);
    }
}
