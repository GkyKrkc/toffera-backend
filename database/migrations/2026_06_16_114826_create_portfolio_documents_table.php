<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_item_id')
                ->constrained('portfolio_items')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('file_name');          // orijinal dosya adı
            $table->string('path');               // storage path
            $table->string('url');                // public URL
            $table->string('mime_type', 100);     // application/pdf, image/jpeg vs
            $table->unsignedInteger('size')->default(0); // byte
            $table->string('label', 100)->nullable(); // "Ekspertiz", "Tapu", "Sigorta"

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->timestamps();

            $table->index('portfolio_item_id');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_documents');
    }
};
