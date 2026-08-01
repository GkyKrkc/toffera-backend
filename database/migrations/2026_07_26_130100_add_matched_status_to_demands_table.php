<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'matched': talep sahibi bir teklifi kabul etti ama satış henüz
 * onaylanmadı (ön anlaşma). Acente vazgeçerse demand tekrar 'active'
 * olur; talep sahibi satışı onaylarsa 'completed' olur (kesin final).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE demands MODIFY status ENUM('active', 'matched', 'completed', 'cancelled', 'expired') DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE demands MODIFY status ENUM('active', 'completed', 'cancelled', 'expired') DEFAULT 'active'");
    }
};
