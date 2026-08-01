<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NOT: bu dosya bilerek 2026_07_10_222157_create_billable_products_table.php'den
     * SONRA çalışacak şekilde adlandırıldı (222200 > 222157) — billable_product_id
     * için FK kısıtı burada inline tanımlanabilsin diye. Eskiden bu migration
     * daha erken bir timestamp'te (222150) idi ve billable_products henüz
     * yokken FK kurmaya çalıştığı için migrate:fresh'te "referenced table
     * doesn't exist" hatası veriyordu — bu yeniden adlandırma + inline FK
     * ile o sorun kökten çözüldü (ayrı bir "sonradan FK ekle" migration'ına
     * gerek kalmadı).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('billable_product_id')->nullable()->constrained();
            $table->decimal('amount', 10, 2);
            $table->string('gateway'); // 'paytr' | 'iyzico'
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable(); // gateway'den dönen ham cevap — anlaşmazlık/destek için
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
