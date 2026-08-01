<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hangi kategorilere/kaç adet portföy ekleyebileceğini belirleyen grup.
            // Bireysel kayıtta otomatik atanır, ticari kayıtta kullanıcı seçer
            // (bkz. RegisterController::setAccountType — henüz güncellenmedi).
            // agent_type (emlakci/galerici/her_ikisi) ENUM olduğu için yeni iş
            // kolları (plaza, rent a car...) buraya sığmıyordu — bu yüzden ayrı,
            // serbestçe genişleyebilen bir tablo tercih edildi.
            $table->foreignId('account_type_group_id')
                ->nullable()
                ->after('agent_type')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_type_group_id');
        });
    }
};
