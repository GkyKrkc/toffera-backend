<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * İl / ilçe bayilik atamaları. Bir kullanıcı (role=dealer) birden
     * fazla satıra sahip olabilir (ör. bir il + o ilin bazı ilçeleri),
     * ama bir İL için her zaman TEK bir aktif il-seviyeli bayi olabilir
     * (bkz. RegionDealerResource form validasyonu — DB seviyesinde değil
     * uygulama seviyesinde uygulanıyor, çünkü is_active ile
     * pasifleştirme/yeniden atama senaryosunu bozan sert bir unique index
     * MySQL'de partial index desteklemediği için mümkün değil).
     *
     * region_type='ilce' olan bir satır varsa, o ilçedeki talep/teklif
     * onayı İL bayisinden bu ilçe bayisine devredilir (bkz.
     * RegionDealerService::resolveDealerForRegion). İlçe bayisinin ayrı
     * bir gelir payı YOK — %30 her zaman il bayisine gider (kullanıcı
     * kararı: "sadece yetki devri").
     */
    public function up(): void
    {
        Schema::create('region_dealers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('region_type', ['il', 'ilce']);
            $table->string('il'); // örn. "Kahramanmaraş" — features->il ile birebir aynı yazımda tutulmalı
            $table->string('ilce')->nullable(); // region_type=ilce ise zorunlu, il ise null

            // Sadece region_type=il için anlamlı; ilçe bayisinde gösterim
            // amaçlı kalabilir ama gelir hesaplamasında kullanılmıyor.
            $table->decimal('revenue_share_percent', 5, 2)->default(30.00);

            $table->boolean('can_approve_demands')->default(true);
            $table->boolean('can_approve_offers')->default(true);
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['il', 'region_type', 'is_active']);
            $table->index(['il', 'ilce', 'region_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_dealers');
    }
};
