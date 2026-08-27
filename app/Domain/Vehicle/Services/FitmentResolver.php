<?php

namespace App\Domain\Vehicle\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Vehicle\DTOs\FitmentResult;
use App\Domain\Vehicle\Enums\FitmentStatus;
use App\Domain\Vehicle\Models\ProductFitment;
use App\Domain\Vehicle\Models\VehicleTrim;

class FitmentResolver
{
    /** Resolves a product against an exact vehicle trim using exclusion-first, most-specific matching. */
    public function resolve(Product $product, VehicleTrim $trim, ?int $variantId = null): FitmentResult
    {
        $trim->loadMissing(['generation.model.make', 'engine']);
        $rules = $product->fitments()->get();

        if ($rules->isEmpty()) {
            return new FitmentResult(FitmentStatus::Conditional, 'برای این قطعه هنوز اطلاعات سازگاری ثبت نشده است.');
        }

        $matches = $rules->filter(fn (ProductFitment $rule) => $this->matches($rule, $trim, $variantId));
        if ($matches->isEmpty()) {
            return new FitmentResult(FitmentStatus::Incompatible, 'این قطعه با خودروی انتخاب‌شده سازگار نیست.');
        }

        $ordered = $matches->sortByDesc(fn (ProductFitment $rule) => $this->specificity($rule));
        $exclusion = $ordered->first(fn (ProductFitment $rule) => $rule->is_exclusion);
        if ($exclusion) {
            return new FitmentResult(FitmentStatus::Incompatible, $exclusion->notes ?: 'این ترکیب خودرو از سازگاری قطعه مستثنا شده است.', $exclusion->getKey(), (int) $exclusion->confidence);
        }

        /** @var ProductFitment $rule */
        $rule = $ordered->first();

        return new FitmentResult($rule->status, $rule->notes ?: $this->messageFor($rule->status), $rule->getKey(), (int) $rule->confidence);
    }

    /** Checks every populated fitment dimension; null dimensions intentionally behave as wildcards. */
    private function matches(ProductFitment $rule, VehicleTrim $trim, ?int $variantId): bool
    {
        $generation = $trim->generation;
        $model = $generation->model;
        $make = $model->make;

        if ($rule->product_variant_id && (int) $rule->product_variant_id !== (int) $variantId) {
            return false;
        }
        if ($rule->vehicle_make_id && (int) $rule->vehicle_make_id !== (int) $make->getKey()) {
            return false;
        }
        if ($rule->vehicle_model_id && (int) $rule->vehicle_model_id !== (int) $model->getKey()) {
            return false;
        }
        if ($rule->vehicle_generation_id && (int) $rule->vehicle_generation_id !== (int) $generation->getKey()) {
            return false;
        }
        if ($rule->vehicle_trim_id && (int) $rule->vehicle_trim_id !== (int) $trim->getKey()) {
            return false;
        }
        if ($rule->vehicle_engine_id && (int) $rule->vehicle_engine_id !== (int) $trim->vehicle_engine_id) {
            return false;
        }
        if ($rule->from_year && $trim->year < $rule->from_year) {
            return false;
        }
        if ($rule->to_year && $trim->year > $rule->to_year) {
            return false;
        }

        return true;
    }

    /** Scores rules so a trim-specific rule wins over a broad make/model rule. */
    private function specificity(ProductFitment $rule): int
    {
        return collect([
            $rule->product_variant_id,
            $rule->vehicle_make_id,
            $rule->vehicle_model_id,
            $rule->vehicle_generation_id,
            $rule->vehicle_trim_id,
            $rule->vehicle_engine_id,
            $rule->from_year,
            $rule->to_year,
        ])->filter()->count() * 1000 + (int) $rule->confidence;
    }

    /** Converts the stored compatibility status to a clear Persian storefront message. */
    private function messageFor(FitmentStatus $status): string
    {
        return match ($status) {
            FitmentStatus::Compatible => 'این قطعه با خودروی انتخاب‌شده سازگار است.',
            FitmentStatus::Conditional => 'سازگاری این قطعه نیازمند بررسی تیپ، موتور یا شرایط تکمیلی است.',
            FitmentStatus::Incompatible => 'این قطعه با خودروی انتخاب‌شده سازگار نیست.',
        };
    }
}
