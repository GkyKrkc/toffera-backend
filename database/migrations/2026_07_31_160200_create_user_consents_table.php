<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kullanıcının hangi yasal metni, hangi versiyonda, ne zaman ve hangi
// IP'den onayladığının kaydı (KVKK'nın "onayı ispat yükümlülüğü" için).
// legal_document_type bilerek FK DEĞİL — belge satırı silinse/yeniden
// oluşturulsa bile geçmiş onay kaydı bozulmasın diye sadece type + version
// tutuluyor (bkz. LegalDocument, User::pendingConsents()).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('legal_document_type');
            $table->unsignedInteger('version');
            $table->timestamp('accepted_at');
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'legal_document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
