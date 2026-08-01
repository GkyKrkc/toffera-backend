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

            // Serbest string (enum DEĞİL) — kategori bazlı dinamik belge
            // sistemine (Category.required_documents → runtime'da üretilen
            // belge listesi) izin vermek için. AgentDocument::TYPE_LABELS
            // sabitindeki 4 klasik değer (isyeri_belgesi, ticaret_sicili,
            // esnaf_oda_kaydi, vergi_levhasi) hâlâ fallback etiket olarak
            // kullanılıyor, ama kolonun kendisi artık bunlarla sınırlı değil
            // (ör. "galeri_ruhsati" gibi yeni belge türleri de eklenebilir).
            $table->string('document_type');

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