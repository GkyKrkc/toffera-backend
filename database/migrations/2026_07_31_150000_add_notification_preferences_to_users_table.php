<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bildirim tercihleri (SMS/e-posta, kategori bazlı) — bkz. SettingsPage.jsx
// "Bildirim Tercihleri" sekmesi, User::wantsChannel(), AppNotification::via().
// Nullable: hiç kaydedilmemişse TÜM kanallar açık kabul edilir (varsayılan).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('is_banned');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
