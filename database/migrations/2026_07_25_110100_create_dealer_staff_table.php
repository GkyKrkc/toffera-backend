<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bayilik departman personeli. Her satır, bir kullanıcıyı (dealer_staff
     * rolü) bir bayilik atamasına (region_dealers — il/ilçe) ve bir
     * departmana bağlar. Departman, hangi kategori alt ağacındaki
     * talep/teklifleri görebileceğini belirler (bkz. RegionDealerService):
     *
     *   - 'galeri' → Vasıta kategori alt ağacı
     *   - 'emlak'  → Gayrimenkul kategori alt ağacı
     *   - 'hepsi'  → kategori kısıtı yok (bölge kısıtı hâlâ geçerli)
     *
     * region_dealer_id, bayi SAHİBİNİN kendi region_dealers kaydına işaret
     * eder — personel kendi bölge ataması yapmaz, sahibinin bölgesini
     * (il/ilçe + onay yetkisi) miras alır, sadece departman bazında
     * daralır.
     *
     * Muhasebe (dealer_revenue_shares) bu tabloyla HİÇ ilişkilendirilmedi
     * — bilinçli: sadece region_dealer_id (bayi sahibi) üzerinden erişim
     * kontrolü yapılıyor, personel oraya hiç giremiyor.
     */
    public function up(): void
    {
        Schema::create('dealer_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_dealer_id')->constrained()->cascadeOnDelete();

            $table->enum('department', ['galeri', 'emlak', 'hepsi']);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'region_dealer_id']);
            $table->index(['region_dealer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_staff');
    }
};
