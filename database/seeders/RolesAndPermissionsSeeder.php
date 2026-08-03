<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]
            ->forgetCachedPermissions();

        // ── Permissions ───────────────────────────────────────────────
        $permissions = [
            // Stock
            'view stock',
            'add stock',
            'remove stock',
            'transfer stock',
            'confirm stock arrival',

            // Products
            'view products',
            'manage products',

            // Purchase Requests
            'create purchase request',
            'verify purchase request',
            'approve purchase request',

            // Purchase Orders
            'view orders',
            'manage orders',
            'receive stock from order',

            // Stock Transfers
            'create stock transfer',
            'approve stock transfer',
            'confirm stock transfer arrival',

            // Equipment Units
            'manage equipment units',

            // Customers
            'view customers',
            'manage customers',
            'issue equipment',
            'return equipment',

            // Suppliers
            'view suppliers',
            'manage suppliers',

            // Warehouses
            'view all warehouses',
            'view own warehouse only',

            // Users
            'manage users',

            // Reports & Logs
            'view reports',
            'view activity log',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ── Admin — everything ────────────────────────────────────────
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $admin->syncPermissions(Permission::all());

        // ── HR Verifier — step B ──────────────────────────────────────
        $verifier = Role::firstOrCreate(['name' => 'HR Verifier']);
        $verifier->syncPermissions([
            'view stock',
            'view products',
            'verify purchase request',
            'view orders',
            'view suppliers',
            'view customers',
            'view all warehouses',
            'view reports',
            'view activity log',
        ]);

        // ── HR Approver — step C ──────────────────────────────────────
        $approver = Role::firstOrCreate(['name' => 'HR Approver']);
        $approver->syncPermissions([
            'view stock', 'add stock', 'remove stock', 'transfer stock',
            'view products', 'manage products',
            'approve purchase request',
            'view orders', 'manage orders', 'receive stock from order',
            'create stock transfer', 'approve stock transfer',
            'manage equipment units',
            'view customers', 'manage customers',
            'view suppliers', 'manage suppliers',
            'view all warehouses',
            'view reports', 'view activity log',
        ]);

        // ── Technical Team PP ─────────────────────────────────────────
        $techPP = Role::firstOrCreate(['name' => 'Technical Team PP']);
        $techPP->syncPermissions([
            'view stock',
            'view products',
            'create purchase request',
            'confirm stock arrival',
            'view customers', 'manage customers',
            'issue equipment', 'return equipment',
            'view all warehouses',
        ]);

        // ── Technical Team Poipet ─────────────────────────────────────
        $techPoipet = Role::firstOrCreate(['name' => 'Technical Team Poipet']);
        $techPoipet->syncPermissions([
            'view stock',
            'view products',
            'create purchase request',
            'confirm stock transfer arrival',
            'view customers', 'manage customers',
            'issue equipment', 'return equipment',
            'view own warehouse only',
        ]);

        // Assign Admin to first user
        $firstUser = User::first();
        if ($firstUser && !$firstUser->hasRole('Admin')) {
            $firstUser->syncRoles(['Admin']);
        }

        $this->command->info('✅ Roles created successfully.');
        $this->command->table(
            ['Role', 'Purpose'],
            [
                ['Admin',                'Full access — everything'],
                ['HR Verifier',          'Step B — review and verify PRs'],
                ['HR Approver',          'Step C — approve PRs, manage POs and stock'],
                ['Technical Team PP',    'Create PRs, manage PP customers'],
                ['Technical Team Poipet','Create PRs, manage Poipet customers'],
            ]
        );
    }
}