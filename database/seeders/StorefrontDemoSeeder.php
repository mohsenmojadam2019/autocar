<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorefrontDemoSeeder extends Seeder
{
    /** Seeds a deterministic, presentation-ready automotive storefront for development and launch previews. */
    public function run(): void
    {
        $categories = $this->categories();
        $brands = $this->brands();
        $warehouseId = $this->warehouse();

        $products = [
            ['name' => 'روغن موتور Mobil 1 مدل 5W-30 چهار لیتری', 'brand' => 'mobil-1', 'category' => 'oil-filter', 'sku' => 'AC-MOB-5W30-4L', 'oem' => 'MOB1-5W30', 'price' => 24500000, 'compare' => 28900000, 'image' => 13065692],
            ['name' => 'لنت ترمز جلو Hi-Q سری Premium', 'brand' => 'hi-q', 'category' => 'brake-system', 'sku' => 'AC-HIQ-FBP-206', 'oem' => 'SP1152', 'price' => 6800000, 'compare' => 7600000, 'image' => 30470930],
            ['name' => 'باتری 66 آمپر Bosch S4', 'brand' => 'bosch', 'category' => 'electrical', 'sku' => 'AC-BOS-S4-66', 'oem' => '0092S40070', 'price' => 39500000, 'compare' => 41900000, 'image' => 4374843],
            ['name' => 'شمع ایریدیوم NGK مدل LFR6AIX-11', 'brand' => 'ngk', 'category' => 'engine', 'sku' => 'AC-NGK-LFR6AIX', 'oem' => '6619', 'price' => 3200000, 'compare' => 3480000, 'image' => 13065692],
            ['name' => 'فیلتر روغن Mann Filter سری W712/94', 'brand' => 'mann-filter', 'category' => 'oil-filter', 'sku' => 'AC-MAN-W71294', 'oem' => 'W712/94', 'price' => 2950000, 'compare' => 3350000, 'image' => 24182219],
            ['name' => 'کیت تسمه تایم Gates PowerGrip', 'brand' => 'gates', 'category' => 'engine', 'sku' => 'AC-GAT-K015', 'oem' => 'K015587XS', 'price' => 17150000, 'compare' => 18900000, 'image' => 12330652],
            ['name' => 'کمک فنر جلو Tokico سری Performance', 'brand' => 'tokico', 'category' => 'suspension', 'sku' => 'AC-TOK-FR-01', 'oem' => 'B3245', 'price' => 21400000, 'compare' => 22900000, 'image' => 34277924],
            ['name' => 'دیسک ترمز جلو Brembo استاندارد', 'brand' => 'brembo', 'category' => 'brake-system', 'sku' => 'AC-BRE-DISC-01', 'oem' => '09.A047.10', 'price' => 18600000, 'compare' => 19900000, 'image' => 30470930],
            ['name' => 'چراغ جلو کامل Valeo', 'brand' => 'valeo', 'category' => 'body', 'sku' => 'AC-VAL-HL-01', 'oem' => '044921', 'price' => 23600000, 'compare' => 24900000, 'image' => 8985310],
            ['name' => 'فیلتر هوای Mahle سری LX', 'brand' => 'mahle', 'category' => 'oil-filter', 'sku' => 'AC-MAH-LX-01', 'oem' => 'LX1780', 'price' => 3780000, 'compare' => 4100000, 'image' => 24182219],
            ['name' => 'کویل جرقه Bosch مدل High Energy', 'brand' => 'bosch', 'category' => 'electrical', 'sku' => 'AC-BOS-COIL-01', 'oem' => '0221504470', 'price' => 8950000, 'compare' => 9700000, 'image' => 13065692],
            ['name' => 'لاستیک رادیال Premium سایز 205/55R16', 'brand' => 'genuine-parts', 'category' => 'consumables', 'sku' => 'AC-TIRE-2055516', 'oem' => '20555R16', 'price' => 42800000, 'compare' => 46900000, 'image' => 34357292],
        ];

        $seeded = collect();
        foreach ($products as $index => $data) {
            $mediaPath = sprintf('https://images.pexels.com/photos/%1$d/pexels-photo-%1$d.jpeg?auto=compress&cs=tinysrgb&w=900', $data['image']);

            $product = Product::query()->updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'brand_id' => $brands[$data['brand']]->id,
                    'name' => $data['name'],
                    'name_en' => $data['sku'],
                    'slug' => Str::slug($data['sku']),
                    'oem_code' => $data['oem'],
                    'manufacturer_code' => $data['oem'],
                    'authenticity' => $index % 3 === 0 ? 'oem' : 'company',
                    'status' => 'active',
                    'summary' => 'قطعه منتخب اتوکار با اطلاعات فنی شفاف، امکان بررسی سازگاری خودرو و ضمانت اصالت.',
                    'description' => 'این محصول به‌عنوان داده نمایشی فروشگاه اتوکار ثبت شده است. پیش از خرید، خودروی خود را انتخاب کنید تا سازگاری قطعه با مدل، نسل و تیپ خودرو بررسی شود.',
                    'warranty' => 'ضمانت اصالت و سلامت فیزیکی',
                    'return_days' => 7,
                    'weight_grams' => 900 + ($index * 130),
                    'purchase_price' => (int) round($data['price'] * .72),
                    'sale_price' => $data['price'],
                    'compare_at_price' => $data['compare'],
                    'wholesale_price' => (int) round($data['price'] * .91),
                    'is_taxable' => true,
                    'tax_rate' => 10,
                    'published_at' => now()->subDays(20 - min(19, $index)),
                    'meta_title' => $data['name'].' | اتوکار',
                    'meta_description' => 'خرید '.$data['name'].' با بررسی سازگاری خودرو و ضمانت اصالت از اتوکار.',
                ],
            );

            $product->categories()->syncWithoutDetaching([$categories[$data['category']]->id => ['is_primary' => true]]);
            $product->media()->updateOrCreate(['position' => 0], ['disk' => 'public', 'path' => $mediaPath, 'type' => 'image', 'alt' => $data['name'], 'is_primary' => true]);

            DB::table('stock_items')->updateOrInsert(
                ['warehouse_id' => $warehouseId, 'product_id' => $product->id, 'product_variant_id' => null],
                ['on_hand' => 18 + ($index * 3), 'reserved' => 0, 'damaged' => 0, 'reorder_point' => 4, 'updated_at' => now(), 'created_at' => now()],
            );

            $seeded->push($product);
        }

        if ($seeded->count() >= 6) {
            foreach ($seeded->take(5) as $position => $product) {
                if ($product->id !== $seeded[5]->id) {
                    DB::table('product_relations')->updateOrInsert(
                        ['product_id' => $seeded[5]->id, 'related_product_id' => $product->id, 'type' => 'related'],
                        ['position' => $position],
                    );
                }
            }
        }

        $promotionId = DB::table('automatic_promotions')->where('slug', 'demo-weekly-offer')->value('id');
        if (! $promotionId) {
            $promotionId = DB::table('automatic_promotions')->insertGetId([
                'name' => 'پیشنهاد ویژه اتوکار',
                'slug' => 'demo-weekly-offer',
                'discount_type' => 'percentage',
                'discount_value' => 8,
                'minimum_quantity' => 1,
                'badge_text' => '۸٪ تخفیف',
                'priority' => 50,
                'is_active' => true,
                'stackable' => false,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(14),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach ($seeded->take(5) as $product) {
            DB::table('automatic_promotion_product')->updateOrInsert(['automatic_promotion_id' => $promotionId, 'product_id' => $product->id]);
        }
    }

    /** @return array<string, Category> */
    private function categories(): array
    {
        $definitions = [
            'engine' => ['موتور', 'bi bi-gear-wide-connected', 10],
            'brake-system' => ['ترمز', 'bi bi-disc', 20],
            'suspension' => ['جلوبندی و تعلیق', 'bi bi-wrench-adjustable-circle', 30],
            'oil-filter' => ['روغن و فیلتر', 'bi bi-droplet', 40],
            'electrical' => ['برق و الکترونیک', 'bi bi-lightning-charge', 50],
            'body' => ['بدنه و تزئینات', 'bi bi-car-front', 60],
            'climate' => ['سیستم تهویه', 'bi bi-fan', 70],
            'tools' => ['ابزار و تجهیزات', 'bi bi-tools', 80],
            'consumables' => ['لوازم مصرفی', 'bi bi-box2-heart', 90],
        ];

        $items = [];
        foreach ($definitions as $slug => [$name, $icon, $position]) {
            $items[$slug] = Category::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'icon' => $icon, 'position' => $position, 'depth' => 0, 'is_active' => true]);
        }

        return $items;
    }

    /** @return array<string, Brand> */
    private function brands(): array
    {
        $definitions = [
            'bosch' => ['Bosch', 'DE'],
            'valeo' => ['Valeo', 'FR'],
            'mann-filter' => ['Mann Filter', 'DE'],
            'mobil-1' => ['Mobil 1', 'US'],
            'mahle' => ['Mahle', 'DE'],
            'ngk' => ['NGK', 'JP'],
            'gates' => ['Gates', 'US'],
            'tokico' => ['Tokico', 'JP'],
            'hi-q' => ['Hi-Q', 'KR'],
            'brembo' => ['Brembo', 'IT'],
            'genuine-parts' => ['Genuine Parts', null],
        ];

        $items = [];
        foreach ($definitions as $slug => [$name, $country]) {
            $items[$slug] = Brand::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'name_en' => $name, 'country_code' => $country, 'is_active' => true]);
        }

        return $items;
    }

    private function warehouse(): int
    {
        DB::table('warehouses')->updateOrInsert(
            ['code' => 'MAIN'],
            ['name' => 'انبار مرکزی اتوکار', 'city' => 'تهران', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        return (int) DB::table('warehouses')->where('code', 'MAIN')->value('id');
    }
}
