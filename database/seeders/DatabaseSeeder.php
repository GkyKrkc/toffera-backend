<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Önce roller ve yetkiler oluşturulmalı
        $this->call([
            RolesAndPermissionsSeeder::class,
            CategorySeeder::class,
        ]);

        // Admin kullanıcısı — factory ile oluştur, role ata
        $admin = \App\Models\User::factory()->create([
            'name'               => 'Gökay KARAKOÇ',
            'email'              => 'admin@toffera.com',
            'phone'              => '05000000000',
            'password'           => \Illuminate\Support\Facades\Hash::make('Admin123!'),
            'status'             => 'active',
            'phone_verified_at'  => now(),
        ]);

        $admin->assignRole('admin');

        $this->command->info("Admin oluşturuldu: {$admin->email}");
    }
}