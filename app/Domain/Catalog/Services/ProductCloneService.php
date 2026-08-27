<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCloneService
{
    /** Deep-clones a product including categories, variants, media, specifications and fitment rules. */
    public function clone(Product $source, ?string $name = null): Product
    {
        return DB::transaction(function () use ($source, $name): Product {
            $source->loadMissing(['categories', 'variants', 'media', 'attributeValues', 'fitments']);

            $copy = $source->replicate();
            $copy->name = $name ?: $source->name.' - کپی';
            $copy->slug = $this->uniqueSlug($source->slug.'-copy');
            $copy->sku = $this->uniqueSku($source->sku.'-COPY');
            $copy->status = 'draft';
            $copy->published_at = null;
            $copy->save();

            $copy->categories()->sync($source->categories->mapWithKeys(
                fn ($category) => [$category->getKey() => ['is_primary' => (bool) $category->pivot->is_primary]]
            )->all());

            foreach ($source->variants as $variant) {
                $newVariant = $variant->replicate();
                $newVariant->product_id = $copy->getKey();
                $newVariant->sku = $this->uniqueSku($variant->sku.'-COPY');
                $newVariant->barcode = null;
                $newVariant->save();
            }

            foreach ($source->media as $media) {
                $copy->media()->create($media->only(['disk', 'path', 'type', 'alt', 'position', 'is_primary']));
            }

            foreach ($source->attributeValues as $value) {
                $copy->attributeValues()->create($value->only(['attribute_id', 'attribute_option_id', 'value']));
            }

            foreach ($source->fitments as $fitment) {
                $attributes = $fitment->getAttributes();
                unset($attributes['id'], $attributes['product_id'], $attributes['created_at'], $attributes['updated_at']);
                $attributes['product_variant_id'] = null;
                $copy->fitments()->create($attributes);
            }

            return $copy->fresh(['categories', 'variants', 'media', 'attributeValues', 'fitments']);
        });
    }

    /** Produces a unique product slug without relying on database-specific upsert syntax. */
    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'product-copy';
        $candidate = $slug;
        $suffix = 2;
        while (Product::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }

    /** Produces a unique SKU for copied products and variants. */
    private function uniqueSku(string $base): string
    {
        $candidate = strtoupper($base);
        $suffix = 2;
        while (Product::query()->where('sku', $candidate)->exists() || ProductVariant::query()->where('sku', $candidate)->exists()) {
            $candidate = strtoupper($base).'-'.$suffix++;
        }

        return $candidate;
    }
}
