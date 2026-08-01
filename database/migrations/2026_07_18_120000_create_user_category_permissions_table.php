<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tek gerçek kaynak (single source of truth): bir kullanıcının hangi
     * kategoride portföy ekleyebileceği VE hangi kategoride teklif
     * verebileceği burada tutulur. AccountTypeGroup <-> Category pivot'u
     * artık sadece "şablon" — buraya senkronize edilir, doğrudan
     * okunmaz (bkz. CategoryAccessService).
     *
     * source='group'  → grup senkronundan geldi, grup değişince güncellenir
     * source='manual' → admin elle override etti, grup senkronu bu satıra
     *                    DOKUNMAZ (ta ki admin "grup varsayılanına sıfırla"
     *                    demeden).
     */
    public function up(): void
    {
        Schema::create('user_category_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->boolean('can_add_portfolio')->default(false);
            $table->unsignedInteger('portfolio_limit')->nullable(); // null = sınırsız

            $table->boolean('can_offer')->default(false);

            $table->enum('source', ['group', 'manual'])->default('group');

            $table->timestamps();

            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_category_permissions');
    }
};
