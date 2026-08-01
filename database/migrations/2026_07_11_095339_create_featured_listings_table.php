<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_item_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['vitrin', 'one_cikar']);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('payment_id')->nullable()->constrained();
            $table->timestamps();

            $table->index(['portfolio_item_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_listings');
    }
};
