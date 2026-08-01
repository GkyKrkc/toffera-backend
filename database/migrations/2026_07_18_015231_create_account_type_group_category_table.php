<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_type_group_category', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_type_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            // Bu grubun bu kategoride ekleyebileceği maksimum portföy öğesi sayısı.
            // null = sınırsız. Admin panelinden (AccountTypeGroupResource) yönetilir.
            // Aktif abonelik varsa BillableProduct.portfolio_limit_override /
            // unlimited_portfolio bunu ezebilir (bkz. User::portfolioLimitFor()).
            $table->unsignedInteger('portfolio_limit')->nullable();

            // Bu gruba bu kategori atanınca, gruptaki kullanıcılar bu
            // kategoride varsayılan olarak teklif verebilsin mi?
            // (portfolio_limit'ten bağımsız — portföy ekleme her zaman
            // açık kalır, teklif verme ayrıca işaretlenmeli.)
            $table->boolean('can_offer')->default(false);

            $table->timestamps();

            $table->unique(['account_type_group_id', 'category_id'], 'atg_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_type_group_category');
    }
};
