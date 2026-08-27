<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CatalogTransferService
{
    /** Imports or updates products from CSV using product/category/brand slugs as external identifiers. */
    public function importCsv(string $absolutePath, ?int $userId = null): int
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException('فایل CSV قابل خواندن نیست.');
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('باز کردن فایل CSV ناموفق بود.');
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            throw new RuntimeException('هدر CSV معتبر نیست.');
        }

        $importId = (int) DB::table('catalog_imports')->insertGetId([
            'user_id' => $userId,
            'file_name' => basename($absolutePath),
            'mode' => 'upsert',
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rowNumber = 1;
        $processed = 0;
        $failed = 0;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $payload = array_combine($headers, array_pad($values, count($headers), null));
            if (! is_array($payload)) {
                $failed++;
                $this->recordImportError($importId, $rowNumber, 'تعداد ستون‌ها نامعتبر است.', []);
                continue;
            }

            try {
                $this->upsertProduct($payload);
                $processed++;
            } catch (Throwable $exception) {
                $failed++;
                $this->recordImportError($importId, $rowNumber, $exception->getMessage(), $payload);
            }
        }
        fclose($handle);

        DB::table('catalog_imports')->where('id', $importId)->update([
            'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
            'total_rows' => $processed + $failed,
            'processed_rows' => $processed,
            'failed_rows' => $failed,
            'updated_at' => now(),
        ]);

        return $importId;
    }

    /** Exports the complete catalog with stable slugs for safe round trips between environments. */
    public function exportCsv(): string
    {
        $path = 'exports/catalog-'.now()->format('Ymd-His').'.csv';
        $stream = fopen('php://temp', 'w+b');
        fputcsv($stream, ['slug', 'name', 'sku', 'oem_code', 'brand_slug', 'category_slugs', 'sale_price', 'status', 'authenticity']);

        Product::query()->with(['brand', 'categories'])->orderBy('id')->chunkById(500, function ($products) use ($stream): void {
            foreach ($products as $product) {
                fputcsv($stream, [
                    $product->slug,
                    $product->name,
                    $product->sku,
                    $product->oem_code,
                    $product->brand?->slug,
                    $product->categories->pluck('slug')->implode('|'),
                    $product->sale_price,
                    $product->status->value,
                    $product->authenticity->value,
                ]);
            }
        });

        rewind($stream);
        Storage::disk('local')->put($path, stream_get_contents($stream) ?: '');
        fclose($stream);

        return $path;
    }

    /** Applies a safe bulk update to products addressed by slug. */
    public function bulkUpdate(array $productSlugs, array $changes): int
    {
        $allowed = array_intersect_key($changes, array_flip(['status', 'sale_price', 'compare_at_price', 'wholesale_price', 'is_taxable', 'tax_rate']));
        if ($allowed === []) {
            throw new RuntimeException('هیچ فیلد قابل تغییر گروهی ارسال نشده است.');
        }

        return Product::query()->whereIn('slug', array_unique($productSlugs))->update($allowed + ['updated_at' => now()]);
    }

    /** Upserts one CSV record and resolves all taxonomy references through slugs. */
    private function upsertProduct(array $row): void
    {
        $slug = trim((string) ($row['slug'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));
        if ($slug === '' || $name === '' || $sku === '') {
            throw new RuntimeException('slug، name و sku اجباری هستند.');
        }

        DB::transaction(function () use ($row, $slug, $name, $sku): void {
            $brand = null;
            if ($brandSlug = trim((string) ($row['brand_slug'] ?? ''))) {
                $brand = Brand::query()->where('slug', $brandSlug)->firstOrFail();
            }

            $product = Product::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'sku' => $sku,
                    'oem_code' => $row['oem_code'] ?: null,
                    'brand_id' => $brand?->id,
                    'sale_price' => max(0, (int) ($row['sale_price'] ?? 0)),
                    'status' => $row['status'] ?: 'draft',
                    'authenticity' => $row['authenticity'] ?: 'company',
                ],
            );

            $categorySlugs = array_filter(array_map('trim', explode('|', (string) ($row['category_slugs'] ?? ''))));
            $categoryIds = Category::query()->whereIn('slug', $categorySlugs)->pluck('id')->all();
            if (count($categoryIds) !== count(array_unique($categorySlugs))) {
                throw new RuntimeException('حداقل یک category_slug پیدا نشد.');
            }
            $product->categories()->sync($categoryIds);
        });
    }

    /** Persists row-level import errors for downloadable admin diagnostics. */
    private function recordImportError(int $importId, int $row, string $message, array $payload): void
    {
        DB::table('catalog_import_errors')->insert([
            'catalog_import_id' => $importId,
            'row_number' => $row,
            'message' => Str::limit($message, 2000),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
