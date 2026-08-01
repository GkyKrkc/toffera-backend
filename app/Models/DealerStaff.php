<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bayilik departman personeli. bkz. RegionDealerService — bölge+departman
 * scope'u oradan yönetiliyor, bu model sadece veri + departman→kategori
 * çözümlemesi taşıyor.
 */
class DealerStaff extends Model
{
    protected $table = 'dealer_staff';

    protected $fillable = [
        'user_id',
        'region_dealer_id',
        'department',
        'is_active',
    ];

    /** Departman adı → kök kategori slug'ı. 'hepsi' burada YOK (kısıt yok anlamına geliyor, çözümlenmiyor). */
    private const DEPARTMENT_ROOT_SLUGS = [
        'galeri' => 'vasita',
        'emlak'  => 'gayrimenkul',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Bayi sahibi bir personel tanımladığında, o kullanıcı panele
        // girebilsin diye 'dealer_staff' rolünü otomatik veriyoruz —
        // RegionDealer::booted() ile aynı desen. Rolü GERİ ALMIYORUZ.
        static::created(function (DealerStaff $staff) {
            $user = $staff->user;
            if ($user && !$user->hasRole('dealer_staff') && !$user->hasRole('dealer')) {
                $user->assignRole('dealer_staff');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function regionDealer(): BelongsTo
    {
        return $this->belongsTo(RegionDealer::class);
    }

    public function isAllDepartments(): bool
    {
        return $this->department === 'hepsi';
    }

    /**
     * Bu personelin departmanına karşılık gelen kategori ID'leri
     * (kök + tüm alt ağaç). 'hepsi' için null döner — çağıran taraf
     * bunu "kısıt yok" olarak yorumlamalı.
     */
    public function departmentCategoryIds(): ?array
    {
        if ($this->isAllDepartments()) {
            return null;
        }

        $slug = self::DEPARTMENT_ROOT_SLUGS[$this->department] ?? null;

        if (!$slug) {
            return [];
        }

        $root = Category::where('slug', $slug)->first();

        return $root ? $root->selfAndDescendantIds() : [];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
