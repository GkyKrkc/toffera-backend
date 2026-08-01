<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            [
                'name' => 'Gayrimenkul',
                'slug' => 'gayrimenkul',
                'icon' => 'building-2',
                'required_documents' => [
                    ['key' => 'ticaret_sicili', 'label' => 'Ticaret Sicil Kaydı', 'required' => true],
                    ['key' => 'vergi_levhasi', 'label' => 'Vergi Levhası', 'required' => true],
                ],
                'children' => [
                    [
                        'name' => 'Satılık',
                        'slug' => 'gayrimenkul-satilik',
                        'children' => [
                            $this->evDaireLeaf('gayrimenkul-satilik-ev-daire'),
                            [
                                'name' => 'Arsa / Tarla',
                                'slug' => 'gayrimenkul-satilik-arsa-tarla',
                                'form_schema' => [
                                    ['key' => 'metrekare', 'label' => 'Metrekare', 'type' => 'number', 'placeholder' => 'Örn: 500'],
                                    ['key' => 'imar_durumu', 'label' => 'İmar Durumu', 'type' => 'select',
                                        'options' => ['İmarlı', 'İmarsız', 'Tarla', 'Bağ-Bahçe']],
                                ],
                            ],
                            [
                                'name' => 'Devremülk',
                                'slug' => 'gayrimenkul-satilik-devremulk',
                                'form_schema' => [
                                    ['key' => 'donem', 'label' => 'Dönem', 'type' => 'text', 'placeholder' => 'Örn: Yaz sezonu'],
                                    ['key' => 'metrekare', 'label' => 'Metrekare', 'type' => 'number', 'placeholder' => 'Örn: 60'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Kiralık',
                        'slug' => 'gayrimenkul-kiralik',
                        'children' => [
                            $this->evDaireLeaf('gayrimenkul-kiralik-ev-daire'),
                            [
                                'name' => 'İş Yeri',
                                'slug' => 'gayrimenkul-kiralik-is-yeri',
                                'form_schema' => [
                                    ['key' => 'metrekare', 'label' => 'Metrekare', 'type' => 'number', 'placeholder' => 'Örn: 150'],
                                    ['key' => 'kullanim_alani', 'label' => 'Kullanım Alanı', 'type' => 'select',
                                        'options' => ['Ofis', 'Dükkan', 'Depo', 'Atölye', 'Diğer']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Vasıta',
                'slug' => 'vasita',
                'icon' => 'car',
                'required_documents' => [
                    ['key' => 'ticaret_sicili', 'label' => 'Ticaret Sicil Kaydı', 'required' => true],
                    ['key' => 'vergi_levhasi', 'label' => 'Vergi Levhası', 'required' => true],
                    ['key' => 'galeri_ruhsati', 'label' => 'Galeri Ruhsatı', 'required' => false],
                ],
                'children' => [
                    [
                        'name' => 'Sıfır Araç',
                        'slug' => 'vasita-sifir-arac',
                        'children' => [
                            $this->otomobilLeaf('vasita-sifir-arac-otomobil', withYilKm: false),
                            $this->motosikletLeaf('vasita-sifir-arac-motosiklet', withYilKm: false),
                        ],
                    ],
                    [
                        'name' => '2. El Araç',
                        'slug' => 'vasita-2el-arac',
                        'children' => [
                            $this->otomobilLeaf('vasita-2el-arac-otomobil', withYilKm: true),
                            [
                                'name' => 'Arazi, SUV & Pick-up',
                                'slug' => 'vasita-2el-arac-suv-pickup',
                                'form_schema' => $this->araciOrtakAlanlar(withYilKm: true),
                            ],
                            $this->motosikletLeaf('vasita-2el-arac-motosiklet', withYilKm: true),
                        ],
                    ],
                ],
            ],
        ];

        foreach ($tree as $node) {
            $this->createNode($node, null);
        }

        $this->command->info('Kategori ağacı oluşturuldu.');
    }

    /** Bir düğümü (ve varsa çocuklarını recursive) oluşturur. */
    private function createNode(array $node, ?int $parentId): void
    {
        $children = $node['children'] ?? [];
        unset($node['children']);

        $category = Category::firstOrCreate(
            ['slug' => $node['slug']],
            [
                'parent_id'          => $parentId,
                'name'               => $node['name'],
                'icon'               => $node['icon'] ?? null,
                'form_schema'        => $node['form_schema'] ?? null,
                'required_documents' => $node['required_documents'] ?? null,
                'is_active'          => true,
            ]
        );

        foreach ($children as $index => $child) {
            $child['sort_order'] = $index;
            $this->createNode($child, $category->id);
        }
    }

    // ── Tekrarlanan yaprak şablonları ────────────────────────

    private function evDaireLeaf(string $slug): array
    {
        return [
            'name' => 'Ev / Daire',
            'slug' => $slug,
            'form_schema' => [
                ['key' => 'oda_sayisi', 'label' => 'Oda Sayısı', 'type' => 'select',
                    'options' => ['1+0', '1+1', '2+1', '3+1', '4+1', '5+1 ve üzeri']],
                ['key' => 'metrekare', 'label' => 'Metrekare', 'type' => 'number', 'placeholder' => 'Örn: 120'],
                ['key' => 'bina_yasi', 'label' => 'Bina Yaşı', 'type' => 'select',
                    'options' => ['Sıfır', '1-5 Yıl', '6-10 Yıl', '11-20 Yıl', '20+ Yıl']],
                ['key' => 'kat', 'label' => 'Kat', 'type' => 'text', 'placeholder' => 'Örn: 3. Kat'],
                ['key' => 'isitma', 'label' => 'Isıtma', 'type' => 'select',
                    'options' => ['Doğalgaz', 'Kombi', 'Merkezi', 'Klima', 'Diğer']],
            ],
        ];
    }

    private function araciOrtakAlanlar(bool $withYilKm): array
    {
        $fields = [
            ['key' => 'marka', 'label' => 'Marka', 'type' => 'text', 'placeholder' => 'Örn: Toyota'],
            ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'placeholder' => 'Örn: Corolla'],
        ];

        if ($withYilKm) {
            $fields[] = ['key' => 'yil', 'label' => 'Model Yılı', 'type' => 'select',
                'options' => array_map('strval', range((int) date('Y'), 2000))];
            $fields[] = ['key' => 'km', 'label' => 'Maksimum KM', 'type' => 'number', 'placeholder' => 'Örn: 100000'];
        }

        $fields[] = ['key' => 'yakit', 'label' => 'Yakıt Tipi', 'type' => 'select',
            'options' => ['Benzin', 'Dizel', 'LPG', 'Elektrik', 'Hibrit']];
        $fields[] = ['key' => 'vites', 'label' => 'Vites', 'type' => 'select',
            'options' => ['Manuel', 'Otomatik', 'Yarı Otomatik']];

        return $fields;
    }

    private function otomobilLeaf(string $slug, bool $withYilKm): array
    {
        return [
            'name' => 'Otomobil',
            'slug' => $slug,
            'form_schema' => $this->araciOrtakAlanlar($withYilKm),
        ];
    }

    private function motosikletLeaf(string $slug, bool $withYilKm): array
    {
        $fields = [
            ['key' => 'marka', 'label' => 'Marka', 'type' => 'text', 'placeholder' => 'Örn: Honda'],
            ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'placeholder' => 'Örn: CBR'],
        ];

        if ($withYilKm) {
            $fields[] = ['key' => 'yil', 'label' => 'Model Yılı', 'type' => 'select',
                'options' => array_map('strval', range((int) date('Y'), 2000))];
            $fields[] = ['key' => 'km', 'label' => 'Maksimum KM', 'type' => 'number', 'placeholder' => 'Örn: 30000'];
        }

        $fields[] = ['key' => 'motor_hacmi', 'label' => 'Motor Hacmi (cc)', 'type' => 'number', 'placeholder' => 'Örn: 150'];

        return ['name' => 'Motosiklet', 'slug' => $slug, 'form_schema' => $fields];
    }
}
