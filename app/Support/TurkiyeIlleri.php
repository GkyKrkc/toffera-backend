<?php

namespace App\Support;

/**
 * 81 ilin sabit listesi — bayilik atama formunda "İl" seçimi için.
 * Serbest metin bırakılmadı çünkü features->il ile BİREBİR aynı yazımda
 * olmazsa (ör. "Kahramanmaras" vs "Kahramanmaraş") bölge eşleştirmesi
 * (RegionDealerService) sessizce hiçbir kaydı bulamaz. İlçe ise (81 il x
 * çok sayıda ilçe listesini PHP tarafında ayrıca tutmamak için) serbest
 * metin bırakıldı — admin, mevcut bir talepteki features->ilce değerini
 * birebir kopyalamalı (bkz. RegionDealerResource form açıklaması).
 */
class TurkiyeIlleri
{
    public static function options(): array
    {
        $iller = [
            'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara', 'Antalya', 'Artvin',
            'Aydın', 'Balıkesir', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa',
            'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Edirne', 'Elazığ', 'Erzincan',
            'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Isparta',
            'Mersin', 'İstanbul', 'İzmir', 'Kars', 'Kastamonu', 'Kayseri', 'Kırklareli', 'Kırşehir',
            'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Kahramanmaraş', 'Mardin', 'Muğla',
            'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Rize', 'Sakarya', 'Samsun', 'Siirt',
            'Sinop', 'Sivas', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Şanlıurfa', 'Uşak',
            'Van', 'Yozgat', 'Zonguldak', 'Aksaray', 'Bayburt', 'Karaman', 'Kırıkkale', 'Batman',
            'Şırnak', 'Bartın', 'Ardahan', 'Iğdır', 'Yalova', 'Karabük', 'Kilis', 'Osmaniye',
            'Düzce',
        ];

        sort($iller, SORT_STRING | SORT_FLAG_CASE);

        return array_combine($iller, $iller);
    }
}
