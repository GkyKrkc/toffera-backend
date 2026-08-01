<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billable_products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();       // 'sub_bireysel_aylik', 'kontor_10', 'vitrin_7gun'
            $table->string('name');
            $table->enum('type', ['subscription', 'credit_pack', 'featured_listing', 'boost']);
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('credit_amount')->nullable();  // kontör paketiyse: kaç kontör verir
            $table->unsignedInteger('offer_quota')->nullable();    // abonelikse: aylık teklif kotası (null = sınırsız)
            $table->unsignedInteger('duration_days')->nullable();  // abonelik/vitrin geçerlilik süresi
            // Bu ürün hangi kategorilerde teklif hakkı veriyor.
            // null = kategori bağımsız (kontör paketleri genelde böyle).
            $table->json('categories')->nullable();

            // Bu ürünü satın alan/abone olan kullanıcı için, grubun pivot
            // limitini EZEN sabit bir portföy limiti. null = ezme, grubun
            // kendi limiti geçerli olur. Örn: "Pro Üyelik" → 30.
            $table->unsignedInteger('portfolio_limit_override')->nullable();

            // true ise portfolio_limit_override'dan bile önce gelir, tüm
            // limitleri kaldırır. Örn: "Mega Üyelik" → sınırsız portföy.
            $table->boolean('unlimited_portfolio')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billable_products');
    }
};
