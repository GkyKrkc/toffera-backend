<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway')->unique(); // 'paytr', 'iyzico'
            $table->text('credentials'); // şifreli saklanır (encrypted:array cast)
            $table->boolean('is_active')->default(false);
            $table->boolean('is_test_mode')->default(true);
            $table->timestamps();
        });

        DB::table('payment_gateway_settings')->insert([
            ['gateway' => 'paytr',  'credentials' => encrypt('{}'), 'is_active' => true,  'is_test_mode' => true, 'created_at' => now(), 'updated_at' => now()],
            ['gateway' => 'iyzico', 'credentials' => encrypt('{}'), 'is_active' => false, 'is_test_mode' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
