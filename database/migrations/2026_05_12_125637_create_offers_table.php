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
        Schema::create('offers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('demand_id')->constrained()->onDelete('cascade');
        $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
        $table->string('price')->nullable();
        $table->text('message');
        $table->string('portfolio_url')->nullable();
        $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
