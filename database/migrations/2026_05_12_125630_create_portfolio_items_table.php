<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');              // vasita | gayrimenkul | elektronik
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('status')->default('available'); // available | reserved | sold
            $table->timestamp('sold_at')->nullable();
            $table->json('features')->nullable();           // teknik detaylar
            $table->string('district')->nullable();         // gayrimenkul lokasyonu

            // Mevcut düz "type" (vasita|gayrimenkul|elektronik) string'i KALDIRILMIYOR,
            // geriye dönük uyumluluk için duruyor. category_id, dinamik kategori
            // ağacına (ve dolayısıyla grup bazlı portföy limitine) bağlanmak için var.
            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Sahiplik belgesi onaylandığında ModerationService::approveDocument()
            // tarafından set edilir (2026-07-14 squash'ında eksik migration olarak
            // tespit edilip eklendi — model zaten bu kolonu bekliyordu).
            $table->timestamp('ownership_verified_at')->nullable();

            // Moderasyon — status (available/reserved/sold) ile KARIŞTIRILMAMALI.
            // Bir öğe hem 'available' hem 'pending' (henüz onaylanmamış) olabilir.
            $table->enum('moderation_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users');
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_note')->nullable();

            $table->timestamps();

            $table->index(['moderation_status']);
            $table->index(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_items');
    }
};
