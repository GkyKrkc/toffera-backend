<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_demand_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_item_id');
            $table->unsignedBigInteger('demand_id');
            $table->timestamps();

            $table->unique(['portfolio_item_id', 'demand_id'], 'unique_portfolio_demand');

            $table->foreign('portfolio_item_id')
                ->references('id')
                ->on('portfolio_items')
                ->onDelete('cascade');

            $table->foreign('demand_id')
                ->references('id')
                ->on('demands')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_demand_notifications');
    }
};
