<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortfolioItem extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'type', 'title', 'description',
        'price', 'status', 'features', 'district',
        'sold_at', 'ownership_verified_at',
        'moderation_status', 'moderated_by', 'moderated_at', 'moderation_note',
    ];

    protected $casts = [
        'features'              => 'array',
        'notified_demand_ids'   => 'array',
        'price'                 => 'decimal:2',
        'sold_at'               => 'datetime',
        'ownership_verified_at' => 'datetime',
    ];

    // ── İlişkiler ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PortfolioImage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PortfolioDocument::class)->latest();
    }

    public function coverImage(): HasMany
    {
        return $this->hasMany(PortfolioImage::class)->where('is_cover', true)->limit(1);
    }

    // ── Scope'lar ──

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    // ── Bildirim Takibi ──

    public function isNotifiedFor(int $demandId): bool
    {
        $ids = $this->notified_demand_ids ?? [];
        return in_array($demandId, $ids);
    }

    public function markNotifiedFor(int $demandId): void
    {
        $ids = $this->notified_demand_ids ?? [];
        if (!in_array($demandId, $ids)) {
            $ids[] = $demandId;
            $this->update(['notified_demand_ids' => $ids]);
        }
    }

    public function offers()
    {
        return $this->hasMany(Offer::class, 'portfolio_item_id');
    }

    public function hasActiveOffers(): bool
    {
        return $this->offers()->whereIn('status', ['pending', 'reviewing'])->exists();
    }

    // ── Süreli teklif hakkı (kontör bazlı bireysel satıcılar için) ──

    public function offerGrants()
    {
        return $this->hasMany(ItemOfferGrant::class);
    }

    public function hasActiveOfferGrant(): bool
    {
        return $this->offerGrants()->where('ends_at', '>', now())->exists();
    }

    public function isOwnershipVerified(): bool
    {
        return $this->ownership_verified_at !== null;
    }

    public function isModerationApproved(): bool
    {
        return $this->moderation_status === 'approved';
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
