<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    /** Seeds stable granular permissions and synchronizes every system role with its minimum required privileges. */
    public function run(): void
    {
        $groups = [
            'catalog' => ['catalog.view', 'catalog.manage', 'catalog.price', 'catalog.cost'],
            'orders' => ['orders.view', 'orders.manage', 'orders.refund'],
            'inventory' => ['inventory.view', 'inventory.manage', 'inventory.adjust'],
            'customers' => ['customers.view', 'customers.manage', 'customers.export'],
            'marketing' => ['marketing.view', 'marketing.manage', 'marketing.send'],
            'content' => ['content.view', 'content.manage'],
            'moderation' => ['moderation.view', 'moderation.manage'],
            'shipping' => ['shipping.view', 'shipping.manage'],
            'finance' => ['finance.view', 'finance.manage', 'finance.refund'],
            'wholesale' => ['wholesale.view', 'wholesale.manage'],
            'reports' => ['reports.view', 'reports.export'],
            'settings' => ['settings.view', 'settings.manage', 'security.manage'],
        ];

        $permissions = collect();
        foreach ($groups as $group => $slugs) {
            foreach ($slugs as $slug) {
                $permissions->put(
                    $slug,
                    Permission::query()->updateOrCreate(
                        ['slug' => $slug],
                        ['name' => $slug, 'group' => $group],
                    ),
                );
            }
        }

        $roles = [
            'super-admin' => [
                'name' => 'مدیر کل',
                'permissions' => $permissions->keys()->all(),
            ],
            'order-manager' => [
                'name' => 'مدیر سفارش',
                'permissions' => ['orders.view', 'orders.manage', 'customers.view', 'inventory.view', 'shipping.view', 'shipping.manage', 'finance.view'],
            ],
            'warehouse' => [
                'name' => 'انباردار',
                'permissions' => ['inventory.view', 'inventory.manage', 'inventory.adjust', 'catalog.view', 'orders.view', 'shipping.view', 'shipping.manage'],
            ],
            'accountant' => [
                'name' => 'حسابدار',
                'permissions' => ['orders.view', 'orders.refund', 'finance.view', 'finance.manage', 'finance.refund', 'reports.view', 'reports.export'],
            ],
            'support' => [
                'name' => 'پشتیبان',
                'permissions' => ['orders.view', 'customers.view', 'customers.manage', 'moderation.view'],
            ],
            'content-manager' => [
                'name' => 'مدیر محتوا',
                'permissions' => ['catalog.view', 'content.view', 'content.manage', 'moderation.view', 'moderation.manage', 'marketing.view'],
            ],
            'marketing-manager' => [
                'name' => 'مدیر بازاریابی',
                'permissions' => ['customers.view', 'marketing.view', 'marketing.manage', 'marketing.send', 'content.view', 'reports.view'],
            ],
            'wholesale-manager' => [
                'name' => 'مدیر فروش عمده',
                'permissions' => ['catalog.view', 'catalog.price', 'customers.view', 'wholesale.view', 'wholesale.manage', 'orders.view', 'reports.view'],
            ],
        ];

        foreach ($roles as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'is_system' => true],
            );

            $role->permissions()->sync(
                collect($definition['permissions'])
                    ->map(fn (string $permission) => $permissions->get($permission)?->id)
                    ->filter()
                    ->values()
                    ->all(),
            );
        }
    }
}
