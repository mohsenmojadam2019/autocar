<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Seeds deterministic development data without creating production credentials. */
    public function run(): void
    {
        $this->call([CatalogSeeder::class, VehicleSeeder::class]);

        User::factory()->create([
            'name' => 'AutoCar Admin',
            'email' => 'admin@autocar.local',
        ]);
    }
}
