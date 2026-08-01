<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demand_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('portfolio_item_id')
                ->nullable()
                ->constrained('portfolio_items')
                ->nullOnDelete();
            $table->string('price')->nullable();
            $table->text('message');
            $table->string('portfolio_url')->nullable();

            $table->enum('status', ['pending', 'reviewing', 'accepted', 'rejected'])->default('pending');

            // 'sold_elsewhere' | 'owner_declined' | null
            $table->string('rejected_reason')->nullable();

            // "satış nedeniyle kapandı" bildirimi gönderildi mi? (job idempotency)
            $table->timestamp('closed_notified_at')->nullable();

            // Moderasyon — admin onayı olmadan teklif, demandOffers()
            // listesinde talep sahibine hiç görünmez. offers.status (talep
            // sahibinin kararı) ile KARIŞTIRILMAMALI.
            $table->enum('moderation_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users');
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_note')->nullable();

            $table->timestamps();

            $table->index(['portfolio_item_id', 'rejected_reason', 'closed_notified_at'], 'offers_sale_closure_idx');
            $table->index(['demand_id', 'status'], 'offers_demand_status_idx');
            $table->index(['moderation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
