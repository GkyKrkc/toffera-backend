<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_type_groups', function (Blueprint $table) {
            $table->id();

            // Örn: "Bireysel Talep", "Galericiler (2. El)", "Plazalar (Sıfır Araç)", "Rent A Car"
            $table->string('name');
            $table->string('slug')->unique();

            // individual: kayıt olurken otomatik atanır, belge istenmez (varsayılan).
            // commercial: kayıt sırasında kullanıcı seçer, kategoriye bağlı belge istenir.
            $table->enum('kind', ['individual', 'commercial'])->default('commercial');

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_type_groups');
    }
};
