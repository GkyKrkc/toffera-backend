<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// quick_messages — admin panelinden (Filament) yönetilen hazır/otomatik
// mesaj önerileri. Örn: "Fiyatta pazarlık var mı?", "%10 indirim talep
// ediyorum." Mesajlaşma panelinde metin kutusunun üstünde tek tıkla
// gönderilebilen küçük çipler olarak gösterilir. Deploy gerektirmeden
// admin tarafından eklenip/düzenlenebilsin diye veritabanında tutuluyor.
// ─────────────────────────────────────────────────────────────
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_messages', function (Blueprint $table) {
            $table->id();
            $table->string('label');   // panelde çipte görünen kısa metin
            $table->text('body');      // gönderilecek asıl mesaj metni (label ile aynı olabilir)
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_messages');
    }
};
