<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Havale/EFT ödemelerinde hangi şirket hesabının gösterildiğini (raporlama/
// eşleştirme için) ve kullanıcının isteğe bağlı olarak eklediği notu
// (gönderen adı, referans vb. — banka ekstresiyle eşleştirmede admin'e
// yardımcı olur) saklamak için. PayTR/iyzico ödemelerinde her ikisi de
// null kalır.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('billable_product_id')
                ->constrained()->nullOnDelete();
            $table->string('customer_note')->nullable()->after('raw_response');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn('customer_note');
        });
    }
};
