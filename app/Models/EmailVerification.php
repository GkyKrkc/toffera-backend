<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmailVerification extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'token',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    // ── İlişkiler ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Yardımcılar ───────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isVerified();
    }

    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }

    // ── Factory metodu ────────────────────────────────────────

    public static function createForUser(User $user): self
    {
        // Varsa eskiyi sil
        static::where('user_id', $user->id)->delete();

        return static::create([
            'user_id'    => $user->id,
            'email'      => $user->email,
            'token'      => Str::random(64),
            'expires_at' => now()->addHours(24),
        ]);
    }
}