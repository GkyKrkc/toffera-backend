<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    protected $fillable = [
        'demand_id',
        'user_id',
        'price',
        'message',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
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

    public function isPending(): bool  { return $this->status === 'pending';  }
    public function isAccepted(): bool { return $this->status === 'accepted'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
}