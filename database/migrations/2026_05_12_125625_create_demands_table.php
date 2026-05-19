<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demands', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('category_id')->constrained();
        $table->string('title');
        $table->string('min_budget')->nullable();
        $table->string('max_budget')->nullable();
        $table->string('district')->nullable();
        $table->string('neighborhood')->nullable();
        $table->json('features'); // Seçilen kategoriye özel veriler burada (JSON)
        $table->string('duration')->default('24'); // Saat cinsinden süre
        $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demands');
    }
};
