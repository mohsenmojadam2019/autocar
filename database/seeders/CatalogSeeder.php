<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /** Seeds a small deterministic automotive taxonomy suitable for local development. */
    public function run(): void
    {
        $engine = Category::query()->firstOrCreate(['slug' => 'engine'], ['name' => 'موتور', 'position' => 10]);
        Category::query()->firstOrCreate(['slug' => 'fuel-system'], ['name' => 'سیستم سوخت‌رسانی', 'parent_id' => $engine->id, 'depth' => 1, 'position' => 10]);
        Category::query()->firstOrCreate(['slug' => 'brake-system'], ['name' => 'سیستم ترمز', 'position' => 20]);
        Category::query()->firstOrCreate(['slug' => 'suspension'], ['name' => 'تعلیق و جلوبندی', 'position' => 30]);
        Category::query()->firstOrCreate(['slug' => 'electrical'], ['name' => 'برق و الکترونیک', 'position' => 40]);
        Category::query()->firstOrCreate(['slug' => 'body'], ['name' => 'بدنه و تزئینات', 'position' => 50]);

        foreach (['Bosch' => 'DE', 'Mann Filter' => 'DE', 'Valeo' => 'FR', 'NGK' => 'JP', 'Genuine Parts' => null] as $name => $country) {
            Brand::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'country_code' => $country]);
        }
    }
}
