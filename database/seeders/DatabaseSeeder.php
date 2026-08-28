<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Seeds deterministic development data without embedding production credentials. */
    public function run(): void
    {
        $this->call([
            AccessControlSeeder::class,
            CatalogSeeder::class,
            VehicleSeeder::class,
            StorefrontDemoSeeder::class,
        ]);

        $admin = User::factory()->create([
            'name' => 'AutoCar Admin',
            'email' => 'admin@autocar.local',
            'mobile' => '09120000000',
        ]);

        if ($role = Role::query()->where('slug', 'super-admin')->first()) {
            $admin->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
