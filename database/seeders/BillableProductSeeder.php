<?php

namespace Database\Seeders;

use App\Models\BillableProduct;
use Illuminate\Database\Seeder;

/**
 * Satın alınabilir tüm ürünler:
 *
 *  - Uzmanlar (galericiler/danışmanlar) için basic/premium/pro aylık
 *    abonelik paketleri — farkları TEKLİF KOTASI değil PORTFÖY LİMİTİ:
 *      basic   → 10 portföy  → 500 TL/ay
 *      premium → 20 portföy  → 1000 TL/ay
 *      pro     → sınırsız portföy → 1500 TL/ay
 *    (bkz. User::portfolioLimitFor() — subscription'ın billableProduct'ından
 *    portfolio_limit_override / unlimited_portfolio okunuyor). Teklif verme
 *    hakkı bu paketlerde sınırlı değil (offer_quota = null).
 *
 *  - Bireysel (uzman olmayan) kullanıcılar için tek seferlik kontör
 *    paketleri — 1 kontör = 1 teklif verme hakkı (bkz.
 *    User::consumeOfferEntitlement()).
 *
 * Fiyatlar admin panelden (Ödeme & Abonelik → Ödenebilir Ürünler) her
 * zaman güncellenebilir — buradakiler ilk kurulum değerleridir.
 */
class BillableProductSeeder extends Seeder
{
    public function run(): void
    {
        $subscriptionPlans = [
            [
                'code'                     => 'basic_monthly',
                'name'                     => 'Basic — 10 Portföy (Aylık)',
                'type'                     => 'subscription',
                'price'                    => 500,
                'offer_quota'              => null, // teklif verme sınırsız, sınır portföy adedinde
                'duration_days'            => 30,
                'categories'               => null,
                'portfolio_limit_override' => 10,
                'unlimited_portfolio'      => false,
                'is_active'                => true,
            ],
            [
                'code'                     => 'premium_monthly',
                'name'                     => 'Premium — 20 Portföy (Aylık)',
                'type'                     => 'subscription',
                'price'                    => 1000,
                'offer_quota'              => null,
                'duration_days'            => 30,
                'categories'               => null,
                'portfolio_limit_override' => 20,
                'unlimited_portfolio'      => false,
                'is_active'                => true,
            ],
            [
                'code'                     => 'pro_monthly',
                'name'                     => 'Pro — Sınırsız Portföy (Aylık)',
                'type'                     => 'subscription',
                'price'                    => 1500,
                'offer_quota'              => null,
                'duration_days'            => 30,
                'categories'               => null,
                'portfolio_limit_override' => null,
                'unlimited_portfolio'      => true,
                'is_active'                => true,
            ],
        ];

        $creditPacks = [
            [
                'code'          => 'credit_pack_10',
                'name'          => '10 Kontör Paketi',
                'type'          => 'credit_pack',
                'price'         => 199,
                'credit_amount' => 10,
                'categories'    => null,
                'is_active'     => true,
            ],
            [
                'code'          => 'credit_pack_20',
                'name'          => '20 Kontör Paketi',
                'type'          => 'credit_pack',
                'price'         => 349,
                'credit_amount' => 20,
                'categories'    => null,
                'is_active'     => true,
            ],
            [
                'code'          => 'credit_pack_30',
                'name'          => '30 Kontör Paketi',
                'type'          => 'credit_pack',
                'price'         => 449,
                'credit_amount' => 30,
                'categories'    => null,
                'is_active'     => true,
            ],
        ];

        foreach ([...$subscriptionPlans, ...$creditPacks] as $product) {
            BillableProduct::updateOrCreate(['code' => $product['code']], $product);
        }

        $this->command->info('Abonelik paketleri (basic/premium/pro) ve kontör paketleri (10/20/30) oluşturuldu/güncellendi.');
    }
}
