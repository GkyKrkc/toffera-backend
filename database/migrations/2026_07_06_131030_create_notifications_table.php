<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel'in standart notifications tablosu.
 * DatabaseNotification modeli ve Notifiable trait bu tabloyu kullanır.
 *
 * Not: `php artisan notifications:table` komutu da aynı migration'ı üretir.
 * Zaten oluşturduysanız bu dosyayı eklemeyin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');                              // Notification sınıfının FQCN'i
            $table->morphs('notifiable');                        // notifiable_type + notifiable_id (User)
            $table->text('data');                                // JSON payload (başlık, mesaj, link, meta)
            $table->timestamp('read_at')->nullable();            // okunma zamanı (null = okunmamış)
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
