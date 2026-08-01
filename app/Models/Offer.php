<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    protected $fillable = [
        'demand_id',
        'user_id',
        'price',
        'message',
        'status',
        'portfolio_item_id',
        'rejected_reason',
        'closed_notified_at',
        'moderation_status',
        'moderated_by',
        'moderated_at',
        'moderation_note',
        'is_favorited',
        'contact_revealed_at',
        'status_before_rejection',
        'sale_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'price'                => 'decimal:2',
            'is_favorited'         => 'boolean',
            'contact_revealed_at'  => 'datetime',
            'sale_confirmed_at'    => 'datetime',
        ];
    }

    // ── İlişkiler ─────────────────────────────────────────────

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** En yeniden en eskiye — teklifin fiyat/mesaj/portföy geçmişi. */
    public function revisions(): HasMany
    {
        return $this->hasMany(OfferRevision::class)->latest('created_at');
    }

    /** Bu teklif üzerinden açılmış mesajlaşma (varsa) — her teklifte en fazla bir tane olur. */
    public function conversation()
    {
        return $this->hasOne(\App\Models\Conversation::class);
    }

    // ── Scope'lar ─────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    // ── Yardımcılar ───────────────────────────────────────────

    public function isPending(): bool   { return $this->status === 'pending';   }
    public function isAccepted(): bool  { return $this->status === 'accepted';  }
    public function isRejected(): bool  { return $this->status === 'rejected';  }
    public function isWithdrawn(): bool { return $this->status === 'withdrawn'; }

    /** Satış onaylandı mı? (talep sahibi 'Satışı Onayla' dedi mi) */
    public function isSaleConfirmed(): bool
    {
        return $this->sale_confirmed_at !== null;
    }

    /**
     * Kabul edilmiş ama satışı henüz onaylanmamış bir teklif — acente bu
     * aşamada hâlâ vazgeçebilir. Satış onaylandıktan sonra (isSaleConfirmed)
     * artık kesinleşmiş sayılır, vazgeçilemez.
     */
    public function isWithdrawable(): bool
    {
        return $this->isAccepted() && !$this->isSaleConfirmed();
    }

    public function isModerationApproved(): bool { return $this->moderation_status === 'approved'; }
    public function isModerationPending(): bool  { return $this->moderation_status === 'pending';  }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function scopeModerationPending($query)
    {
        return $query->where('moderation_status', 'pending');
    }

    public function portfolioItem()
    {
        return $this->belongsTo(\App\Models\PortfolioItem::class, 'portfolio_item_id');
    }

    /** İki güncelleme arasında en az kaç dakika olmalı (spam/hız sınırı). */
    public const UPDATE_COOLDOWN_MINUTES = 10;

    /** Bu teklifin telefon/iletişim bilgisi (kabul edilmiş VEYA talep sahibi erken paylaşmış) görünür durumda mı? */
    public function contactRevealed(): bool
    {
        return $this->isAccepted() || $this->contact_revealed_at !== null;
    }

    /** En son güncellemenin üzerinden UPDATE_COOLDOWN_MINUTES dakika geçti mi? */
    public function canBeUpdatedNow(): bool
    {
        return $this->updated_at === null
            || $this->updated_at->diffInMinutes(now()) >= self::UPDATE_COOLDOWN_MINUTES;
    }

    /** canBeUpdatedNow() false ise, kalan dakika sayısı (en az 1). */
    public function updateCooldownRemainingMinutes(): int
    {
        if (!$this->updated_at) return 0;
        $elapsed = $this->updated_at->diffInMinutes(now());
        return max(1, self::UPDATE_COOLDOWN_MINUTES - $elapsed);
    }
}
