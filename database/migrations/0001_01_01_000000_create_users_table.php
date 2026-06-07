<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique()->index();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password')->nullable(); // SMS girişi için opsiyonel

            $table->string('company_name')->nullable(); // Uzman hesapları için

            // ── Kayıt & onay durumu ──────────────────────────────────
            // pending  : Yeni kayıt (müşteri OTP bekliyor, uzman belge/admin bekliyor)
            // active   : Aktif kullanıcı (müşteri OTP sonrası, uzman admin onayı sonrası)
            // rejected : Admin uzman başvurusunu reddetti
            $table->enum('status', ['pending', 'active', 'rejected'])->default('pending');

            // Sadece agent rolündeki kullanıcılarda dolu
            $table->enum('agent_type', ['emlakci', 'galerici', 'her_ikisi'])->nullable();

            // Admin ret sebebi veya başvuru notu
            $table->text('admin_note')->nullable();

            // ── Üyelik & abonelik ────────────────────────────────────
            $table->string('subscription_plan')->default('free'); // free | basic | premium | pro
            $table->timestamp('subscription_started_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->integer('offer_limit')->default(0); // agent'ın yapabileceği aylık teklif

            // ── Hesap kısıtlama ──────────────────────────────────────
            $table->boolean('is_banned')->default(false);
            $table->text('ban_reason')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};