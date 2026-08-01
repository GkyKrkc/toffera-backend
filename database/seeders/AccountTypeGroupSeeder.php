<?php

namespace Database\Seeders;

use App\Models\AccountTypeGroup;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * İlk kurulum için gereken minimum hesap grubu (uzmanlık tipi) seti.
 * BUNUN DIŞINDA yeni bir uzmanlık tipi eklemek için bu dosyayı DEĞİŞTİRMEK
 * GEREKMEZ — admin panelden (Hesap Grupları → yeni satır + Kategoriler
 * sekmesi) tamamen dinamik olarak eklenir. Bu seeder sadece "veritabanı
 * sıfırlandığında kayıt sistemi hiç çalışmasın" durumunu önlüyor.
 *
 * NOT: "Sıfır Gayrimenkuller için İnşaat Ve Proje Uzmanı" gibi, henüz
 * kategori ağacında karşılığı olmayan tipler BİLEREK buraya eklenmedi —
 * önce ilgili kategori (Gayrimenkul altına) admin panelden oluşturulmalı,
 * sonra bu grup da aynı şekilde panelden eklenebilir.
 *
 * CategorySeeder'dan SONRA çalışmalı (bkz. DatabaseSeeder) — kategori
 * slug'larına göre eşleştirme yapıyor.
 */
class AccountTypeGroupSeeder extends Seeder
{
    public function run(): void
    {
        $allLeafSlugs = Category::leaves()->pluck('slug')->all();

        $groups = [
            [
                'name'            => 'Bireysel Talep',
                'slug'            => 'bireysel-talep',
                'kind'            => 'individual',
                'sort_order'      => 0,
                'categories'      => $allLeafSlugs,
                'portfolio_limit' => 3,
                'can_offer'       => true,
            ],
            [
                'name'            => 'Gayrimenkul Uzmanı',
                'slug'            => 'gayrimenkul-uzmani',
                'kind'            => 'commercial',
                'sort_order'      => 1,
                'categories'      => [
                    'gayrimenkul-satilik-ev-daire',
                    'gayrimenkul-satilik-arsa-tarla',
                    'gayrimenkul-satilik-devremulk',
                    'gayrimenkul-kiralik-ev-daire',
                    'gayrimenkul-kiralik-is-yeri',
                ],
                'portfolio_limit' => 5,
                'can_offer'       => true,
            ],
            [
                'name'            => 'Vasıta Uzmanı',
                'slug'            => 'vasita-uzmani',
                'kind'            => 'commercial',
                'sort_order'      => 2,
                'categories'      => [
                    'vasita-sifir-arac-otomobil',
                    'vasita-sifir-arac-motosiklet',
                    'vasita-2el-arac-otomobil',
                    'vasita-2el-arac-suv-pickup',
                    'vasita-2el-arac-motosiklet',
                ],
                'portfolio_limit' => 5,
                'can_offer'       => true,
            ],
            [
                // Vasıta Uzmanı'nın DAR bir alt kümesi — sadece sıfır araç.
                // Kasıtlı: "sıfır" satan bayi/plazaların "2. el" kategorisinde
                // teklif verememesi için ayrı bir grup (Vasıta Uzmanı'nın
                // pivot'unu daraltmak yerine yeni bir grup — böylece ileride
                // "hem sıfır hem 2.el" satan klasik galericiler Vasıta
                // Uzmanı'nda kalabilir).
                'name'            => 'Sıfır Araçlar için Bayii ve Plaza',
                'slug'            => 'sifir-arac-bayii-plaza',
                'kind'            => 'commercial',
                'sort_order'      => 3,
                'categories'      => [
                    'vasita-sifir-arac-otomobil',
                    'vasita-sifir-arac-motosiklet',
                ],
                'portfolio_limit' => 5,
                'can_offer'       => true,
            ],
        ];

        foreach ($groups as $g) {
            $categorySlugs  = $g['categories'];
            $portfolioLimit = $g['portfolio_limit'];
            $canOffer       = $g['can_offer'];

            $group = AccountTypeGroup::updateOrCreate(
                ['slug' => $g['slug']],
                [
                    'name'       => $g['name'],
                    'kind'       => $g['kind'],
                    'sort_order' => $g['sort_order'],
                    'is_active'  => true,
                ]
            );

            $categoryIds = Category::whereIn('slug', $categorySlugs)->pluck('id', 'slug');

            $sync = [];
            foreach ($categorySlugs as $slug) {
                if (!isset($categoryIds[$slug])) {
                    continue; // kategori bir sebeple yoksa sessizce atla, seeder patlamasın
                }
                $sync[$categoryIds[$slug]] = [
                    'portfolio_limit' => $portfolioLimit,
                    'can_offer'       => $canOffer,
                ];
            }

            $group->categories()->sync($sync);
        }

        $this->command->info('Hesap grupları (Bireysel Talep, Gayrimenkul Uzmanı, Vasıta Uzmanı, Sıfır Araçlar için Bayii ve Plaza) oluşturuldu/güncellendi.');
    }
}
