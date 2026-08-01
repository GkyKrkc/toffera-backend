<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * 'dealer_staff' (bayilik personeli) rolü — bayi sahibinin departman
     * personeli için tanımladığı alt kullanıcılar. 'dealer' rolü
     * migration'ıyla aynı gerekçe: seeder'a güvenme, migration'a yaz.
     *
     * dealer_staff, admin panele (bkz. User::canAccessPanel) dealer ile
     * AYNI yoldan girer ama scope'u DealerStaff kaydındaki bölge+departman
     * ile daha dar sınırlanır (bkz. RegionDealerService). Muhasebe
     * (DealerRevenueShareResource) bu role hiç açılmaz — sadece admin ve
     * dealer (bayi sahibinin kendisi) görür.
     */
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'dealer_staff', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Role::where('name', 'dealer_staff')->where('guard_name', 'web')->delete();
    }
};
