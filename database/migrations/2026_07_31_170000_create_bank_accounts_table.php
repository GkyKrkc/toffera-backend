<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// bank_accounts — Havale/EFT ile ödeme yapmak isteyen kullanıcılara
// gösterilecek şirket banka hesapları. PaymentGatewaySettingResource'un
// aksine (paytr/iyzico — sabit 2 satır, kimlik bilgisi JSON'u) bu tablo
// BİRDEN ÇOK satırı destekler: şirketin farklı bankalarda/para
// birimlerinde birden fazla hesabı olabilir, admin panelden istediği
// kadar ekleyip/kaldırabilir (bkz. BankAccountResource).
// ─────────────────────────────────────────────────────────────
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('banka_adi');           // Örn: "Türkiye İş Bankası"
            $table->string('hesap_sahibi');         // IBAN'daki isimle BİREBİR aynı olmalı — bankalar isim uyuşmazlığında transferi reddedebilir
            $table->string('iban');                 // TR ile başlayan 26 haneli IBAN
            $table->string('sube')->nullable();     // Şube adı/kodu (opsiyonel, bilgi amaçlı)
            $table->text('aciklama')->nullable();    // Örn: "Sadece TL", "Kurumsal ödemeler için" — kullanıcıya gösterilecek not
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true); // Kapalıyken havale/EFT seçeneğinde listelenmez
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
