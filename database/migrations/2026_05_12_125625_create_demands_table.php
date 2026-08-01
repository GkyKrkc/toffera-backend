<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('min_budget')->nullable();
            $table->string('max_budget')->nullable();
            $table->string('district')->nullable();
            $table->string('neighborhood')->nullable();
            $table->json('features'); // Seçilen kategoriye özel veriler burada (JSON)
            $table->string('duration')->default('24'); // Saat cinsinden süre
            $table->timestamp('expires_at')->nullable();
            $table->unsignedMediumInteger('duration_hours')->nullable();

            // Minimum eşleşme oranı — talep sahibi, portföyle en az bu yüzdede
            // eşleşen tekliflerin kendisine ulaşmasını ister (2026-07-13'te eklendi).
            $table->unsignedTinyInteger('min_match_percent')->nullable();

            $table->enum('status', ['active', 'completed', 'cancelled', 'expired'])->default('active');

            // Moderasyon — admin onayı olmadan talep pazaryerinde görünmez
            // (2026-07-14'te eklendi). status ile karıştırılmamalı: status
            // talebin YAŞAM DÖNGÜSÜ, moderation_status admin KARARI.
            $table->string('moderation_status')->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_note')->nullable();

            $table->timestamps();

            $table->index('moderation_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demands');
    }
};
