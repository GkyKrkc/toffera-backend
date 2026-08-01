<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * 'dealer' (bayi) rolü — RoleSeeder yalnızca ilk kurulumda çalıştığı ve
     * production'da tekrar seed edilmesi riskli/unutulabilir olduğu için,
     * bu rol bir migration ile garanti altına alınıyor (bkz. bu projedeki
     * "seeder'a güvenme, migration'a yaz" pratiği).
     */
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'dealer', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Role::where('name', 'dealer')->where('guard_name', 'web')->delete();
    }
};
