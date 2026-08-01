<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Demand extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'district',
        'neighborhood',
        'min_budget',
        'max_budget',
        'features',
        'expires_at',
        'duration_hours',
        'min_match_percent',
        'status',
        'moderation_status',
        'moderated_by',
        'moderated_at',
        'moderation_note',
    ];

    protected function casts(): array
    {
        return [
            'features'       => 'array',
            'min_budget'     => 'decimal:2',
            'max_budget'     => 'decimal:2',
            'expires_at'     => 'datetime',
            'duration_hours' => 'integer',
            'moderated_at' => 'datetime',
        ];
    }

    // ── İlişkiler ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    // ── Scope'lar ─────────────────────────────────────────────

    /**
     * Sadece aktif ve süresi dolmamış ilanlar
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Süresi dolmuş ilanlar (status fark etmez)
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'expired')
                ->orWhere(function ($q2) {
                    $q2->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now());
                });
        });
    }

    public function scopeByCategory($query, string $slug)
    {
        return $query->whereHas('category', fn($q) => $q->where('slug', $slug));
    }

    public function scopeByDistrict($query, string $district)
    {
        return $query->where('district', 'like', "%{$district}%");
    }

    public function scopeByBudget($query, ?string $min, ?string $max)
    {
        if ($min) $query->where('max_budget', '>=', $min);
        if ($max) $query->where('min_budget', '<=', $max);
        return $query;
    }

    // ── Yardımcılar ───────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Bir teklif kabul edildi ama satış henüz onaylanmadı (ön anlaşma). */
    public function isMatched(): bool
    {
        return $this->status === 'matched';
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * İlanın kaç saniye kaldığını döner, süresi yoksa null
     */
    public function remainingSeconds(): ?int
    {
        if (!$this->expires_at) return null;
        $diff = $this->expires_at->getTimestamp() - now()->getTimestamp();
        return max(0, $diff);
    }
}
