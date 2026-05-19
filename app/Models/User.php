<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    // ── Toplu atama ──────────────────────────────────────────
    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone_verified_at',
        'password',
        'company_name',
        'status',
        'agent_type',
        'admin_note',
        'subscription_plan',
        'subscription_started_at',
        'subscription_ends_at',
        'offer_limit',
        'is_banned',
        'ban_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $guard_name = 'web';

    protected function casts(): array
    {
        return [
            'phone_verified_at'      => 'datetime',
            'subscription_started_at'=> 'datetime',
            'subscription_ends_at'   => 'datetime',
            'is_banned'              => 'boolean',
            'offer_limit'            => 'integer',
            'password'               => 'hashed',
        ];
    }

    // ── İlişkiler ─────────────────────────────────────────────
    public function agentDocuments(): HasMany
    {
        return $this->hasMany(AgentDocument::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'phone', 'phone');
    }

    public function emailVerifications(): HasMany
    {
        return $this->hasMany(EmailVerification::class);
    }

    // ── Durum yardımcıları ────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->is_banned;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isBanned(): bool
    {
        return (bool) $this->is_banned;
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    // ── Rol yardımcıları ─────────────────────────────────────
    // Spatie'nin hasRole() var ama kısa alias'lar okunabilirliği artırır

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isBuyer(): bool
    {
        return $this->hasRole('buyer');
    }

    public function isAgent(): bool
    {
        return $this->hasRole('agent');
    }

    // ── Abonelik yardımcıları ─────────────────────────────────

    public function hasActiveSubscription(): bool
    {
        if ($this->subscription_plan === 'free') return false;

        return $this->subscription_ends_at?->isFuture() ?? false;
    }

    /**
     * Agent'ın teklif yapıp yapamayacağını kontrol eder.
     * offer_limit = 0 → sınırsız (örn. premium plan)
     * offer_limit > 0 → bu ay yapılan teklif sayısı kontrol edilir
     */
    public function canMakeOffer(): bool
    {
        if (!$this->isAgent()) return false;
        if ($this->offer_limit === 0) return true;

        $usedThisMonth = Offer::where('user_id', $this->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return $usedThisMonth < $this->offer_limit;
    }

    public function remainingOffers(): int
    {
        if ($this->offer_limit === 0) return PHP_INT_MAX;

        $used = Offer::where('user_id', $this->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return max(0, $this->offer_limit - $used);
    }

    // ── Query Scope'lar ───────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_banned', false);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeBanned($query)
    {
        return $query->where('is_banned', true);
    }

    public function scopeAgents($query)
    {
        return $query->whereHas('roles', fn($q) => $q->where('name', 'agent'));
    }

    public function scopeBuyers($query)
    {
        return $query->whereHas('roles', fn($q) => $q->where('name', 'buyer'));
    }
}