<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('document_type', [
                'isyeri_belgesi',   // İşyeri faaliyet belgesi / kira kontratı
                'ticaret_sicili',   // Ticaret sicil kaydı
                'esnaf_oda_kaydi',  // Esnaf / meslek odası belgesi
                'vergi_levhasi',    // Vergi levhası
            ]);

            $table->string('file_path');           // storage/private disk'teki yol
            $table->string('original_name');       // Yüklenen dosyanın orijinal adı
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // Byte cinsinden

            // Aynı kullanıcı, aynı belge türünü tekrar yükleyince updateOrCreate ile güncellenir
            $table->unique(['user_id', 'document_type']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_documents');
    }
};