<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    /** Seeds stable granular permissions and the default administrative role matrix. */
    public function run(): void
    {
        $groups = [
            'catalog' => ['catalog.view', 'catalog.manage', 'catalog.price', 'catalog.cost'],
            'orders' => ['orders.view', 'orders.manage', 'orders.refund'],
            'inventory' => ['inventory.view', 'inventory.manage', 'inventory.adjust'],
            'customers' => ['customers.view', 'customers.manage', 'customers.export'],
            'marketing' => ['marketing.view', 'marketing.manage', 'marketing.send'],
            'content' => ['content.view', 'content.manage'],
            'reports' => ['reports.view', 'reports.export'],
            'settings' => ['settings.view', 'settings.manage', 'security.manage'],
        ];

        $allIds = [];
        foreach ($groups as $group => $slugs) {
            foreach ($slugs as $slug) {
                $permission = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => $group]);
                $allIds[] = $permission->id;
            }
        }

        $superAdmin = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'مدیر کل', 'is_system' => true]);
        $superAdmin->permissions()->sync($allIds);

        Role::query()->firstOrCreate(['slug' => 'order-manager'], ['name' => 'مدیر سفارش', 'is_system' => true]);
        Role::query()->firstOrCreate(['slug' => 'warehouse'], ['name' => 'انباردار', 'is_system' => true]);
        Role::query()->firstOrCreate(['slug' => 'accountant'], ['name' => 'حسابدار', 'is_system' => true]);
        Role::query()->firstOrCreate(['slug' => 'support'], ['name' => 'پشتیبان', 'is_system' => true]);
        Role::query()->firstOrCreate(['slug' => 'content-manager'], ['name' => 'مدیر محتوا', 'is_system' => true]);
    }
}
