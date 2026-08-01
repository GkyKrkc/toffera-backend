<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // Talep sahibi bu teklifi favorilere eklerse true — kabul/red
            // gibi nihai bir karar değil, sadece kendi karşılaştırması için
            // işaretleme.
            $table->boolean('is_favorited')->default(false)->after('status');

            // Talep sahibi, teklifi henüz kabul etmeden de "İletişim
            // Bilgilerimi Göster" diyerek kendi telefonunu bu teklifi veren
            // acenteye erken paylaşabilir. Null = henüz paylaşılmadı.
            $table->timestamp('contact_revealed_at')->nullable()->after('is_favorited');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['is_favorited', 'contact_revealed_at']);
        });
    }
};
