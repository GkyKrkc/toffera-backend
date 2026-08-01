<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Yasal metinler (Kullanıcı Sözleşmesi, KVKK Aydınlatma Metni, Açık Rıza
// Metni, Ticari Elektronik İleti Onayı) — admin panelden düzenlenebilir,
// içerik her değiştiğinde version otomatik artar (bkz.
// LegalDocumentResource/Pages/EditLegalDocument.php). is_mandatory=true
// olan tipler (user_agreement, kvkk_disclosure) üyelikte zorunlu, diğerleri
// isteğe bağlı — bkz. RegisterController::register(), UserConsent.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique(); // user_agreement | kvkk_disclosure | explicit_consent | commercial_electronic_message
            $table->string('title');
            $table->longText('body'); // {sirket_unvani} gibi merge tag'ler içerebilir
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_mandatory')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
