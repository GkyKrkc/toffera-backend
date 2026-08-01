<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kurumsal bilgiler — tek satırlık (singleton, id=1) ayar kaydı.
 * Admin panelde "Kurumsal Bilgiler" sayfasından düzenlenir (bkz.
 * app/Filament/Admin/Pages/CompanySettingsPage.php). Yasal metinlerdeki
 * {sirket_unvani} gibi merge tag'ler current()'tan beslenir (bkz.
 * LegalDocument::renderedBody()).
 */
class CompanySetting extends Model
{
    protected $fillable = [
        'unvan',
        'adres',
        'telefon',
        'email',
        'faks',
        'mersis_no',
        'vergi_dairesi',
        'vergi_no',
        'kep_adresi',
    ];

    /** Tek satırı getirir, hiç yoksa boş bir tane oluşturur. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    /**
     * LegalDocument::renderedBody()'nin {placeholder} → değer eşlemesi
     * için kullandığı düz dizi. Yeni bir kurumsal alan eklenirse sadece
     * buraya satır eklemek yeterli.
     */
    public function mergeTags(): array
    {
        return [
            'sirket_unvani'   => $this->unvan ?: '[Şirket unvanı girilmedi]',
            'sirket_adresi'   => $this->adres ?: '[Şirket adresi girilmedi]',
            'sirket_telefon'  => $this->telefon ?: '-',
            'sirket_email'    => $this->email ?: '-',
            'sirket_faks'     => $this->faks ?: '-',
            'mersis_no'       => $this->mersis_no ?: '[MERSİS no girilmedi]',
            'vergi_dairesi'   => $this->vergi_dairesi ?: '[Vergi dairesi girilmedi]',
            'vergi_no'        => $this->vergi_no ?: '[Vergi no girilmedi]',
            'kep_adresi'      => $this->kep_adresi ?: '-',
        ];
    }
}
