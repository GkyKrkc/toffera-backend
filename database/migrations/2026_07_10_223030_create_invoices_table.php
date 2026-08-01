<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained();
            $table->string('invoice_number')->unique();
            $table->enum('type', ['e_arsiv', 'e_fatura'])->default('e_arsiv');
            $table->string('pdf_path')->nullable();
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            // Foriba/Paraşüt gibi bir e-fatura API'sine bağlanınca dolacak alanlar:
            $table->string('external_provider')->nullable();   // 'foriba', 'parasut'
            $table->string('external_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
