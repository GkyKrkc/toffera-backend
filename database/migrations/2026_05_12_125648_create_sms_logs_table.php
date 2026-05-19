<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->string('code', 6);

            // OTP amacı: register | login | password_reset
            $table->enum('purpose', ['register', 'login', 'password_reset'])->default('register');

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable(); // Kullanıldığında işaretlenir

            // Brute-force koruması için deneme sayısı
            $table->tinyInteger('attempt_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};