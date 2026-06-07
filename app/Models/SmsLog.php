<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'phone',
        'code',
        'purpose',
        'expires_at',
        'used_at',
        'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'    => 'datetime',
            'used_at'       => 'datetime',
            'attempt_count' => 'integer',
        ];
    }

    // ── Yardımcılar ───────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public function incrementAttempt(): void
    {
        $this->increment('attempt_count');
    }

    // Brute-force koruması: 5 deneme sonrası kodu geçersiz say
    public function isBlocked(): bool
    {
        return $this->attempt_count >= 5;
    }

    // ── Scope'lar ─────────────────────────────────────────────

    public function scopeValid($query)
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function scopeForPhone($query, string $phone)
    {
        return $query->where('phone', $phone);
    }

    public function scopeForPurpose($query, string $purpose)
    {
        return $query->where('purpose', $purpose);
    }
}