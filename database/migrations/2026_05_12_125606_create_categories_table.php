<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Sınırsız iç içe kategori ağacı — null ise ana (kök) kategoridir.
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();

            // Sadece YAPRAK (çocuğu olmayan, somut) kategorilerde dolu olur.
            // Üst/dal kategoriler form_schema taşımaz, sadece organizasyon amaçlıdır.
            $table->json('form_schema')->nullable();

            // form_schema → gerçekten dinamik, admin'in Repeater ile alan
            // tanımladığı jenerik kategoriler için (PortfolioCategoryPage
            // bunu okuyup formu otomatik üretir).
            // form_component → hasar şeması gibi elle kodlanmış, generic
            // Repeater ile üretilemeyecek sabit React component'ler için
            // (örn. "vehicle", "real_estate"). Frontend'de bir registry bu
            // string'i component'e eşler. NULL ise form_schema'ya (ya da
            // onun da boş olduğu durumda tamamen jenerik başlık/açıklama/
            // fiyat formuna) düşülür.
            $table->string('form_component')->nullable();

            // Bu kategoriye agent yetkilendirme başvurusunda hangi belgelerin
            // isteneceği. Her kategori BAĞIMSIZ bir liste tutar, üst kategoriden
            // miras almaz — genelde ana kategori seviyesinde doldurulur.
            // Örn: [{"key":"galeri_ruhsati","label":"Galeri Ruhsatı","required":true}]
            $table->json('required_documents')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
