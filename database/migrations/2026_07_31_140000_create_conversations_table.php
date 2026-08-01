<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// conversations — bir teklif (offer) üzerinden alıcı (talep sahibi) ile
// uzman (teklifi veren acente) arasındaki mesajlaşma iş parçacığı.
//
// Her teklif için EN FAZLA bir konuşma olur (offer_id unique) — aynı
// teklif üzerinde tekrar "Görüşme Başlat"a basılırsa var olan konuşma
// döndürülür, yenisi açılmaz. buyer_id/agent_id, demand/offer'dan
// türetilebilir olsa da (join gerektirmeden) "kullanıcının konuşmaları"
// listesini hızlı çekebilmek için burada da tutuluyor.
//
// ÖNEMLİ: konuşmayı SADECE alıcı başlatabilir (bkz. ConversationController
// @store — agent.approved veya buyer rolü değil, doğrudan "bu teklifin
// demand sahibi misin" kontrolü). Uzman tarafı var olan bir konuşmaya
// mesaj yazabilir ama yeni konuşma açamaz.
// ─────────────────────────────────────────────────────────────
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();

            // 'active' | 'closed' — talep tamamlandığında/iptal olduğunda
            // veya teklif geri çekildiğinde otomatik 'closed' yapılabilir
            // (ileride bir job ile); şimdilik sadece bilgi amaçlı.
            $table->enum('status', ['active', 'closed'])->default('active');

            // Konuşma listesini "son mesaja göre" sıralamak için — her yeni
            // mesajda güncellenir, messages tablosuna join atmadan sıralama
            // yapılabilsin diye burada da tutuluyor.
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index(['buyer_id', 'last_message_at']);
            $table->index(['agent_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
