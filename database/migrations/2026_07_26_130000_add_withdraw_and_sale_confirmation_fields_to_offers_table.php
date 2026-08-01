<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * "Kabul edildi" artık kesin final değil, bir ÖN ANLAŞMA anlamına geliyor.
 * Süreç şöyle işliyor:
 *
 *   1) Talep sahibi teklifi kabul eder → offer.status='accepted',
 *      demand.status='matched' (henüz 'completed' DEĞİL).
 *   2) Acente bu noktada TEKLİFTEN VAZGEÇEBİLİR (araç satılmadı, alıcı
 *      vazgeçti, daha iyi bir alıcı çıktı vb.) → offer.status='withdrawn',
 *      demand tekrar 'active' olur (expires_at DEĞİŞMEZ — talebin orijinal
 *      süresinden kalan zaman neyse o kadar kalır, bonus süre verilmez).
 *      Bu kabul yüzünden otomatik reddedilmiş diğer teklifler de eski
 *      durumlarına (status_before_rejection) geri döner.
 *   3) Talep sahibi gerçek satışın tamamlandığını onaylarsa
 *      → sale_confirmed_at doldurulur, demand.status='completed' (KESİN,
 *      bundan sonra vazgeçilemez).
 *
 * status_before_rejection: accept() sırasında otomatik 'rejected' yapılan
 * kardeş tekliflerin ÖNCEKİ durumu (pending/reviewing) burada saklanır ki
 * vazgeçme durumunda doğru duruma geri dönebilsinler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('status_before_rejection')->nullable()->after('rejected_reason');
            $table->timestamp('sale_confirmed_at')->nullable()->after('status_before_rejection');
        });

        DB::statement("ALTER TABLE offers MODIFY status ENUM('pending', 'reviewing', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE offers MODIFY status ENUM('pending', 'reviewing', 'accepted', 'rejected') DEFAULT 'pending'");

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['status_before_rejection', 'sale_confirmed_at']);
        });
    }
};
