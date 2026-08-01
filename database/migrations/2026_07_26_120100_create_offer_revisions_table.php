<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir teklif her güncellendiğinde (OfferController::update), GÜNCELLEME
 * ÖNCESİ hâli buraya bir satır olarak kaydedilir — böylece "bu teklif kaç
 * kere ve ne şekilde değişti" sorusu tek tek satırlarla cevaplanabilir.
 * Teklifin İLK hâli (oluşturma anı) burada tutulmaz, offers tablosundaki
 * created_at zaten onu temsil eder; bu tablo sadece SONRAKİ değişiklikleri
 * (revizyonları) tutar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->string('message', 500)->nullable();
            $table->foreignId('portfolio_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_revisions');
    }
};
