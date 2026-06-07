<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('city', 100);
            $table->string('district', 100)->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('category_slug', 50)->nullable();
            $table->boolean('notify_new_demand')->default(true);
            $table->timestamps();

            // String uzunluklarını kısalttık — max key 3072 byte aşılmıyor
            $table->unique(
                ['user_id', 'city', 'district', 'neighborhood', 'category_slug'],
                'agent_region_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_regions');
    }
};
