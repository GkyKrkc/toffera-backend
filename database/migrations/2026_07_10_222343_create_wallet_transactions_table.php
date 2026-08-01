<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'topup',            // kontör satın alma
                'offer_spend',      // teklif vermek için 1 kontör harcandı
                'listing_fee',      // vitrin/ilan ücreti
                'boost_fee',        // öne çıkarma ücreti
                'refund',           // iade
                'admin_adjustment', // manuel düzeltme (admin panelden)
            ]);
            $table->integer('amount');            // + veya - (kontör adedi, TL değil)
            $table->integer('balance_after');     // bu hareketten sonraki bakiye — denetim için
            // Bu hareket neyle ilgili? (offer, payment, featured_listing...)
            $table->nullableMorphs('reference');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // users tablosuna hızlı okuma için önbellek bakiye kolonu.
        // GERÇEK KAYNAK her zaman wallet_transactions'daki SUM(amount)'tur;
        // bu kolon sadece performans için, asla doğrudan elle set edilmez.
        Schema::table('users', function (Blueprint $table) {
            $table->integer('credit_balance')->default(0)->after('offer_limit');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('credit_balance');
        });
        Schema::dropIfExists('wallet_transactions');
    }
};
