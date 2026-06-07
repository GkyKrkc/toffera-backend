<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CategorySeeder::class,
        ]);

        // Admin kullanıcısı — factory yerine direkt oluştur
        $admin = \App\Models\User::create([
            'name'              => 'Gökay KARAKOÇ',
            'email'             => 'admin@toffera.com',
            'phone'             => '05000000000',
            'password'          => Hash::make('Admin123!'),
            'status'            => 'active',
            'phone_verified_at' => now(),
        ]);

        $admin->assignRole('admin');

        $this->command->info("Admin oluşturuldu: {$admin->email}");
    }
}