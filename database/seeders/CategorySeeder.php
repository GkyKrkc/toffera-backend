<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'        => 'Gayrimenkul',
                'slug'        => 'gayrimenkul',
                'icon'        => 'building-2',
                'is_active'   => true,
                'form_schema' => [
                    ['key' => 'emlak_tipi',  'label' => 'Emlak Tipi',  'type' => 'select',
                     'options' => ['Daire', 'Villa', 'Arsa', 'İşyeri', 'Bina']],
                    ['key' => 'oda_sayisi',  'label' => 'Oda Sayısı',  'type' => 'select',
                     'options' => ['1+0', '1+1', '2+1', '3+1', '4+1', '5+1 ve üzeri']],
                    ['key' => 'metrekare',   'label' => 'Metrekare',   'type' => 'number', 'placeholder' => 'Örn: 120'],
                    ['key' => 'bina_yasi',   'label' => 'Bina Yaşı',   'type' => 'select',
                     'options' => ['Sıfır', '1-5 Yıl', '6-10 Yıl', '11-20 Yıl', '20+ Yıl']],
                    ['key' => 'kat',         'label' => 'Kat',         'type' => 'text',   'placeholder' => 'Örn: 3. Kat'],
                    ['key' => 'isitma',      'label' => 'Isıtma',      'type' => 'select',
                     'options' => ['Doğalgaz', 'Kombi', 'Merkezi', 'Klima', 'Diğer']],
                ],
            ],
            [
                'name'        => 'Vasıta',
                'slug'        => 'vasita',
                'icon'        => 'car',
                'is_active'   => true,
                'form_schema' => [
                    ['key' => 'arac_tipi',  'label' => 'Araç Tipi',  'type' => 'select',
                     'options' => ['Otomobil', 'SUV', 'Pickup', 'Minivan', 'Ticari', 'Motosiklet']],
                    ['key' => 'marka',      'label' => 'Marka',      'type' => 'text',   'placeholder' => 'Örn: Toyota'],
                    ['key' => 'model',      'label' => 'Model',      'type' => 'text',   'placeholder' => 'Örn: Corolla'],
                    ['key' => 'yil',        'label' => 'Model Yılı', 'type' => 'select',
                     'options' => array_map('strval', range(date('Y'), 2000))],
                    ['key' => 'km',         'label' => 'Maksimum KM', 'type' => 'number', 'placeholder' => 'Örn: 100000'],
                    ['key' => 'yakit',      'label' => 'Yakıt Tipi', 'type' => 'select',
                     'options' => ['Benzin', 'Dizel', 'LPG', 'Elektrik', 'Hibrit']],
                    ['key' => 'vites',      'label' => 'Vites',      'type' => 'select',
                     'options' => ['Manuel', 'Otomatik', 'Yarı Otomatik']],
                ],
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }

        $this->command->info('Kategoriler oluşturuldu.');
    }
}