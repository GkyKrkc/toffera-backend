<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// payment_gateway_settings tablosuna 'havale_eft' satırını ekler — bu,
// PaymentGatewaySettingResource üzerinden admin'in Havale/EFT'yi
// açıp/kapatabildiği ana anahtar (bkz. o resource, canCreate() false
// olduğu için yeni satır SADECE migration ile eklenebilir).
// 'credentials' burada anlamsız (IBAN bilgisi ayrı bir tabloda —
// bkz. 2026_07_31_170000_create_bank_accounts_table), bu yüzden boş
// JSON ile dolduruluyor; is_test_mode de havale için kullanılmıyor.
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('payment_gateway_settings')->where('gateway', 'havale_eft')->exists()) {
            DB::table('payment_gateway_settings')->insert([
                'gateway'      => 'havale_eft',
                'credentials'  => encrypt('{}'),
                'is_active'    => true,
                'is_test_mode' => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('payment_gateway_settings')->where('gateway', 'havale_eft')->delete();
    }
};
