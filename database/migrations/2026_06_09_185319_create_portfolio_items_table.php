<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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
            $table->json('features')->nullable();           // teknik detaylar
            $table->json('images')->nullable();             // resim url'leri
            $table->json('notified_demand_ids')->nullable(); // bildirim gidilen talepler
            $table->string('district')->nullable();         // gayrimenkul lokasyonu
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_items');
    }
};
