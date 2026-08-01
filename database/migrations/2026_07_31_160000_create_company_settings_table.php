<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kurumsal bilgiler — tek satırlık (singleton) ayar tablosu. Yasal
// metinlerdeki {sirket_unvani}, {sirket_adresi} gibi merge tag'ler buradan
// beslenir (bkz. LegalDocument::renderedBody(), CompanySetting::current()).
// Admin > Kurumsal Bilgiler sayfasından yönetilir.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('unvan')->nullable();
            $table->text('adres')->nullable();
            $table->string('telefon')->nullable();
            $table->string('email')->nullable();
            $table->string('faks')->nullable();
            $table->string('mersis_no')->nullable();
            $table->string('vergi_dairesi')->nullable();
            $table->string('vergi_no')->nullable();
            $table->string('kep_adresi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
