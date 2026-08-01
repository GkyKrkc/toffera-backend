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
        Schema::create('portfolio_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_item_id')
                ->constrained('portfolio_items')
                ->cascadeOnDelete();
            $table->string('path');              // storage path: portfolio/images/{item_id}/xxx.jpg
            $table->string('url');               // public URL (Storage::url)
            $table->string('mime_type', 50)->default('image/jpeg');
            $table->unsignedInteger('size')->default(0); // byte
            $table->unsignedSmallInteger('sort_order')->default(0); // sıralama
            $table->boolean('is_cover')->default(false); // kapak fotoğrafı
            $table->timestamps();

            // Sık kullanılan sorgular için index
            $table->index('portfolio_item_id');
            $table->index(['portfolio_item_id', 'is_cover']);
            $table->index(['portfolio_item_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_images');
    }
};
