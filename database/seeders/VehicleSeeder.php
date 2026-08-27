<?php

namespace Database\Seeders;

use App\Domain\Vehicle\Models\VehicleMake;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /** Seeds popular makes only; exact model/generation/trim data remains importable from admin. */
    public function run(): void
    {
        foreach ([
            ['name' => 'ایران خودرو', 'name_en' => 'Iran Khodro', 'slug' => 'iran-khodro'],
            ['name' => 'سایپا', 'name_en' => 'Saipa', 'slug' => 'saipa'],
            ['name' => 'هیوندای', 'name_en' => 'Hyundai', 'slug' => 'hyundai'],
            ['name' => 'کیا', 'name_en' => 'Kia', 'slug' => 'kia'],
            ['name' => 'تویوتا', 'name_en' => 'Toyota', 'slug' => 'toyota'],
        ] as $make) {
            VehicleMake::query()->firstOrCreate(['slug' => $make['slug']], $make);
        }
    }
}
