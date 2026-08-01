<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Her başarılı ödemeden (abonelik veya kontör) hesaplanan bayi payının
     * kaydı. Ödeme bu aşamada OTOMATİK yapılmıyor — admin panelinden bu
     * kayıtlar görülüp elle (banka havalesi vb.) ödendikten sonra
     * status='paid' olarak işaretleniyor (bkz. kullanıcı kararı: "sadece
     * hesapla/raporla").
     */
    public function up(): void
    {
        Schema::create('dealer_revenue_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            // Ödemeyi yapan uzman — region_dealer_id zaten payment'tan
            // türetilebilir ama raporlama/filtreleme kolaylığı için
            // denormalize edildi.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2); // orijinal ödeme tutarı
            $table->decimal('share_percent', 5, 2); // hesaplama anındaki oran (snapshot)
            $table->decimal('share_amount', 12, 2); // amount * share_percent / 100

            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_note')->nullable(); // ör. "Dekont no: ..."

            $table->timestamps();

            $table->unique('payment_id'); // bir ödeme için en fazla 1 pay kaydı
            $table->index(['region_dealer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_revenue_shares');
    }
};
