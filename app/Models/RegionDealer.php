<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * İl/ilçe bayilik ataması. bkz. RegionDealerService — bölge çözümleme
 * (ilçe bayisi varsa öncelikli) ve moderasyon sorgu scope'ları oradan
 * yönetiliyor, bu model sadece veri + basit yardımcılar taşıyor.
 */
class RegionDealer extends Model
{
    protected $fillable = [
        'user_id',
        'region_type',
        'il',
        'ilce',
        'revenue_share_percent',
        'can_approve_demands',
        'can_approve_offers',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'revenue_share_percent' => 'decimal:2',
            'can_approve_demands'   => 'boolean',
            'can_approve_offers'    => 'boolean',
            'is_active'             => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Admin bir kullanıcıya bayilik ataması yaptığında, o kullanıcı
        // panele girebilsin diye 'dealer' rolünü otomatik veriyoruz —
        // rolü elle unutup "neden panele giremiyor" diye uğraşmasın.
        // Rolü GERİ ALMIYORUZ (atama silinse/pasifleştirilse bile) —
        // bu admin'in bilinçli bir kararı olmalı.
        static::created(function (RegionDealer $regionDealer) {
            $user = $regionDealer->user;
            if ($user && !$user->hasRole('dealer')) {
                $user->assignRole('dealer');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revenueShares(): HasMany
    {
        return $this->hasMany(DealerRevenueShare::class);
    }

    public function isIl(): bool
    {
        return $this->region_type === 'il';
    }

    public function isIlce(): bool
    {
        return $this->region_type === 'ilce';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
