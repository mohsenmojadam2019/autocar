<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductMedia;
use App\Domain\Catalog\Models\ProductRelation;
use App\Domain\Catalog\Models\ProductVariant;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Media\LocalMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductWorkbenchController extends Controller
{
    /** Shows media, variants, specifications and merchandising relations for one slug-bound product. */
    public function show(Product $product): View
    {
        $product->load([
            'media',
            'variants',
            'attributeValues.attribute',
            'attributeValues.option',
            'relations.relatedProduct',
        ]);

        return view('admin.products.workbench', [
            'product' => $product,
            'attributes' => Attribute::query()->with('options')->orderBy('position')->orderBy('name')->get(),
            'candidateProducts' => Product::query()->whereKeyNot($product->id)->orderBy('name')->limit(1000)->get(['slug', 'name', 'sku']),
        ]);
    }

    /** Uploads a validated local product image and records presentation metadata. */
    public function storeMedia(Request $request, Product $product, LocalMediaService $media): RedirectResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'alt' => ['nullable', 'string', 'max:250'],
            'is_primary' => ['nullable', 'boolean'],
        ]);
        $asset = $media->storeImage($request->file('image'), 'products/'.$product->slug);

        DB::transaction(function () use ($product, $asset, $data): void {
            $makePrimary = (bool) ($data['is_primary'] ?? false) || ! $product->media()->exists();
            if ($makePrimary) {
                $product->media()->update(['is_primary' => false]);
            }
            $product->media()->create([
                'disk' => 'public',
                'path' => $asset->variant_path ?: $asset->path,
                'type' => 'image',
                'alt' => $data['alt'] ?? $product->name,
                'position' => (int) ($product->media()->max('position') ?? -1) + 1,
                'is_primary' => $makePrimary,
            ]);
        });

        return back()->with('success', 'تصویر محصول اضافه شد.');
    }

    /** Updates ordering, alt text and primary image selection for existing product media. */
    public function updateMedia(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'media' => ['required', 'array'],
            'media.*.id' => ['required', 'integer'],
            'media.*.position' => ['required', 'integer', 'min:0'],
            'media.*.alt' => ['nullable', 'string', 'max:250'],
            'primary_media_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($product, $data): void {
            $owned = $product->media()->pluck('id')->all();
            foreach ($data['media'] as $row) {
                if (! in_array((int) $row['id'], $owned, true)) {
                    continue;
                }
                ProductMedia::query()->whereKey($row['id'])->update([
                    'position' => $row['position'],
                    'alt' => $row['alt'] ?? null,
                    'is_primary' => (int) ($data['primary_media_id'] ?? 0) === (int) $row['id'],
                ]);
            }
        });

        return back()->with('success', 'ترتیب و اطلاعات تصاویر ذخیره شد.');
    }

    /** Deletes one media row only when it belongs to the slug-bound product and cleans its managed file. */
    public function destroyMedia(Product $product, ProductMedia $media, LocalMediaService $localMedia): RedirectResponse
    {
        abort_unless((int) $media->product_id === (int) $product->id, 404);
        $path = $media->path;
        $wasPrimary = $media->is_primary;
        $media->delete();
        $this->deleteManagedImage($path, $localMedia);

        if ($wasPrimary && $next = $product->media()->orderBy('position')->first()) {
            $next->update(['is_primary' => true]);
        }

        return back()->with('success', 'تصویر حذف شد.');
    }

    /** Creates a purchasable product variant with unique SKU and independent pricing. */
    public function storeVariant(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:190'],
            'sku' => ['required', 'string', 'max:100', 'unique:product_variants,sku'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:product_variants,barcode'],
            'purchase_price' => ['nullable', 'integer', 'min:0'],
            'sale_price' => ['required', 'integer', 'min:0'],
            'compare_at_price' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $product->variants()->create($data + [
            'position' => (int) ($product->variants()->max('position') ?? -1) + 1,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('success', 'تنوع محصول اضافه شد.');
    }

    /** Updates an owned variant while preserving globally unique SKU and barcode constraints. */
    public function updateVariant(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        abort_unless((int) $variant->product_id === (int) $product->id, 404);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:190'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variant->id)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('product_variants', 'barcode')->ignore($variant->id)],
            'purchase_price' => ['nullable', 'integer', 'min:0'],
            'sale_price' => ['required', 'integer', 'min:0'],
            'compare_at_price' => ['nullable', 'integer', 'min:0'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $variant->update($data + ['is_active' => (bool) ($data['is_active'] ?? false)]);

        return back()->with('success', 'تنوع ذخیره شد.');
    }

    /** Deletes an owned product variant when no historical protection blocks the database operation. */
    public function destroyVariant(Product $product, ProductVariant $variant): RedirectResponse
    {
        abort_unless((int) $variant->product_id === (int) $product->id, 404);
        $variant->delete();

        return back()->with('success', 'تنوع حذف شد.');
    }

    /** Synchronizes product specifications by stable attribute code and optional option slug/value. */
    public function syncSpecifications(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'specifications' => ['nullable', 'array'],
            'specifications.*.attribute_code' => ['required', 'string', 'exists:attributes,code'],
            'specifications.*.option_slug' => ['nullable', 'string'],
            'specifications.*.value' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($product, $data): void {
            $product->attributeValues()->delete();
            foreach ($data['specifications'] ?? [] as $specification) {
                if (($specification['value'] ?? null) === null && ($specification['option_slug'] ?? null) === null) {
                    continue;
                }
                $attribute = Attribute::query()->where('code', $specification['attribute_code'])->firstOrFail();
                $optionId = null;
                if ($specification['option_slug'] ?? null) {
                    $optionId = $attribute->options()->where('slug', $specification['option_slug'])->value('id');
                }
                $product->attributeValues()->create([
                    'attribute_id' => $attribute->id,
                    'attribute_option_id' => $optionId,
                    'value' => $specification['value'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'مشخصات محصول ذخیره شد.');
    }

    /** Replaces one merchandising relation type using related product slugs rather than public numeric IDs. */
    public function syncRelations(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['related', 'complementary', 'alternative', 'upsell'])],
            'product_slugs' => ['nullable', 'array', 'max:50'],
            'product_slugs.*' => ['string', 'distinct', 'exists:products,slug'],
        ]);
        $targets = Product::query()
            ->whereIn('slug', $data['product_slugs'] ?? [])
            ->whereKeyNot($product->id)
            ->get(['id']);

        DB::transaction(function () use ($product, $data, $targets): void {
            ProductRelation::query()->where('product_id', $product->id)->where('type', $data['type'])->delete();
            foreach ($targets->values() as $position => $target) {
                DB::table('product_relations')->insert([
                    'product_id' => $product->id,
                    'related_product_id' => $target->id,
                    'type' => $data['type'],
                    'position' => $position,
                ]);
            }
        });

        return back()->with('success', 'روابط محصول ذخیره شد.');
    }

    /** Removes a managed image through asset metadata or falls back to the public disk path. */
    private function deleteManagedImage(string $path, LocalMediaService $media): void
    {
        $asset = MediaAsset::query()->where('path', $path)->orWhere('variant_path', $path)->first();
        if ($asset) {
            $media->delete($asset);

            return;
        }
        Storage::disk('public')->delete($path);
    }
}
