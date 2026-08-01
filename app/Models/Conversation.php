<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'demand_id',
        'offer_id',
        'buyer_id',
        'agent_id',
        'status',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    // ── İlişkiler ─────────────────────────────────────────────

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** En eskiden en yeniye — mesaj akışı bu sırayla okunur. */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest('created_at');
    }

    // ── Yardımcılar ───────────────────────────────────────────

    public function isParticipant(int $userId): bool
    {
        return $this->buyer_id === $userId || $this->agent_id === $userId;
    }

    public function otherParty(int $userId): ?int
    {
        if ($this->buyer_id === $userId) return $this->agent_id;
        if ($this->agent_id === $userId) return $this->buyer_id;
        return null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Teklif reddedildiğinde / satış onaylandığında / kabul yüzünden
     * kardeş teklif elendiğinde çağrılır — bkz. OfferController.
     * Zaten kapalıysa gereksiz sorgu atmaz.
     */
    public function close(): void
    {
        if ($this->status !== 'closed') {
            $this->update(['status' => 'closed']);
        }
    }

    /**
     * Kabul yüzünden kapanmış bir konuşma, o kabul geri çekilip (withdraw)
     * kardeş teklif eski durumuna dönünce tekrar açılır — bkz.
     * OfferController::withdraw().
     */
    public function reopen(): void
    {
        if ($this->status !== 'active') {
            $this->update(['status' => 'active']);
        }
    }
}
