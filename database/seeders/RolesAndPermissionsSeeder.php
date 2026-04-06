<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // User management
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'users.impersonate',

            // Customer / portal accounts
            'customers.view', 'customers.create', 'customers.edit',
            'customers.delete', 'customers.suspend', 'customers.notify',
            'customers.payment-gateways.disable',

            // Routers
            'routers.view', 'routers.create', 'routers.edit', 'routers.delete',
            'routers.claim',
            'router-operations.view', 'router-operations.repair',
            'hotspot-payments.support',

            // Subscriptions & payments
            'subscriptions.view', 'subscriptions.manage',
            'invoices.view', 'invoices.download',

            // Hotspot / WifiUsers
            'hotspot.view', 'hotspot.manage',

            // Plans
            'plans.view', 'plans.create', 'plans.edit', 'plans.delete',

            // Reporting & exports (admin / reseller)
            'reports.view', 'reports.export',

            // Settings
            'settings.view', 'settings.edit',

            // Activity log
            'activity-log.view',

            // Referral
            'referral.view',

            // Payment settings
            'payment-settings.view', 'payment-settings.edit',

            // Notifications
            'notifications.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $reseller = Role::firstOrCreate(['name' => 'reseller', 'guard_name' => 'web']);
        $customer = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $adminPermissions = [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'users.impersonate',
            'customers.view', 'customers.create', 'customers.edit',
            'customers.delete', 'customers.suspend', 'customers.notify',
            'customers.payment-gateways.disable',
            'routers.view', 'routers.create', 'routers.edit', 'routers.delete',
            'router-operations.view', 'router-operations.repair',
            'hotspot-payments.support',
            'subscriptions.view', 'subscriptions.manage',
            'invoices.view', 'invoices.download',
            'hotspot.view', 'hotspot.manage',
            'plans.view', 'plans.create', 'plans.edit', 'plans.delete',
            'reports.view', 'reports.export',
            'settings.view', 'settings.edit',
            'activity-log.view',
        ];

        $resellerPermissions = [
            'customers.view', 'customers.create', 'customers.edit', 'customers.suspend',
            'routers.view', 'routers.create', 'routers.edit',
            'router-operations.view', 'router-operations.repair',
            'hotspot-payments.support',
            'subscriptions.view', 'subscriptions.manage',
            'invoices.view', 'invoices.download',
            'hotspot.view', 'hotspot.manage',
            'plans.view',
            'reports.view', 'reports.export',
            'activity-log.view',
        ];

        $customerPermissions = [
            'routers.view', 'routers.claim',
            'subscriptions.view',
            'invoices.view', 'invoices.download',
            'referral.view',
            'payment-settings.view', 'payment-settings.edit',
            'notifications.view',
        ];

        $superAdmin->syncPermissions(Permission::all());
        $admin->syncPermissions($adminPermissions);
        $reseller->syncPermissions($resellerPermissions);
        $customer->syncPermissions($customerPermissions);
    }
}
