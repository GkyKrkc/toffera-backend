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
        // Cache temizle
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Tüm yetkiler ─────────────────────────────────────
        $permissions = [
            'demand.create',
            'demand.view.own',
            'demand.view.market',
            'demand.cancel',
            'demand.manage',
            'offer.create',
            'offer.view.own',
            'offer.view.received',
            'offer.accept',
            'offer.reject',
            'offer.manage',
            'profile.view',
            'profile.edit',
            'category.view',
            'category.manage',
            'user.view',
            'user.manage',
            'agent.approve',
            'agent.reject',
        ];

        // Her yetkiyi hem sanctum hem web guard için oluştur
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ── Roller — hem sanctum hem web guard ───────────────
        //$guards = ['sanctum', 'web'];
        $guards = ['web'];

        foreach ($guards as $guard) {
            // ADMIN — tam yetki
            $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
            $admin->syncPermissions(
                Permission::where('guard_name', $guard)->get()
            );

            // BUYER
            $buyer = Role::firstOrCreate(['name' => 'buyer', 'guard_name' => $guard]);
            $buyer->syncPermissions(
                Permission::where('guard_name', $guard)
                    ->whereIn('name', [
                        'demand.create',
                        'demand.view.own',
                        'demand.cancel',
                        'offer.view.received',
                        'offer.accept',
                        'offer.reject',
                        'profile.view',
                        'profile.edit',
                        'category.view',
                    ])->get()
            );

            // AGENT
            $agent = Role::firstOrCreate(['name' => 'agent', 'guard_name' => $guard]);
            $agent->syncPermissions(
                Permission::where('guard_name', $guard)
                    ->whereIn('name', [
                        'demand.view.market',
                        'offer.create',
                        'offer.view.own',
                        'profile.view',
                        'profile.edit',
                        'category.view',
                    ])->get()
            );
        }

        $this->command->info('Roller ve yetkiler oluşturuldu (sanctum + web guard).');
    }
}