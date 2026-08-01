<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Giriş yapmış bir kullanıcının "bayi olmak istiyorum" başvurusu.
     * İlçe boş bırakılırsa il bayiliği, doldurulursa o ilçe için ilçe
     * bayiliği başvurusu sayılır (bkz. RegionDealerService::
     * approveApplication). Onaylanınca gerçek RegionDealer kaydı burdan
     * TÜRETİLİR — bu tablo sadece başvuru/inceleme sürecini tutar,
     * yetkilendirme mantığı hiçbir yerde bu tabloya bakmaz.
     */
    public function up(): void
    {
        Schema::create('dealer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('il');
            $table->string('ilce')->nullable();
            $table->text('motivation');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_applications');
    }
};
