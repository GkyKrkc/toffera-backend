<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billable_product_id')->constrained();
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            // Bu dönemde kaç teklif kullanıldı — billable_products.offer_quota
            // ile karşılaştırılır. Ayın başında sıfırlanır (scheduled job).
            $table->unsignedInteger('offers_used_this_period')->default(0);
            $table->timestamp('period_resets_at')->nullable();
            $table->foreignId('payment_id')->nullable(); // hangi ödeme bunu satın aldı
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // ── Mevcut users.subscription_plan verisini yeni sisteme taşı ──
        // Önce mevcut planları billable_products'a "sanal ürün" olarak kaydet,
        // sonra her kullanıcının aktif planını subscriptions'a backfill et.
        // Bu SADECE 'free' olmayan, aktif abonelikleri olan kullanıcılar için
        // çalışır — hiçbir mevcut kullanıcının erişimi kesilmez.
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
