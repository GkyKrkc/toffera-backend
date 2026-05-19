<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class SubscriptionService
{
    // ── Plan tanımları ────────────────────────────────────────
    public const PLANS = [
        'free' => [
            'label'       => 'Ücretsiz',
            'offer_limit' => 3,       // Ayda 3 teklif
            'price'       => 0,
        ],
        'basic' => [
            'label'       => 'Temel',
            'offer_limit' => 20,      // Ayda 20 teklif
            'price'       => 299,     // TL
        ],
        'premium' => [
            'label'       => 'Premium',
            'offer_limit' => 50,      // Ayda 50 teklif
            'price'       => 599,
        ],
        'pro' => [
            'label'       => 'Pro',
            'offer_limit' => 0,       // Sınırsız (0 = sınırsız)
            'price'       => 999,
        ],
    ];

    // ── Plan bilgisi ─────────────────────────────────────────

    public function getPlan(string $plan): array
    {
        return self::PLANS[$plan] ?? self::PLANS['free'];
    }

    public function getAllPlans(): array
    {
        return self::PLANS;
    }

    // ── Abonelik aktif etme / yükseltme ──────────────────────

    public function activate(User $user, string $plan, int $months = 1): void
    {
        if (!array_key_exists($plan, self::PLANS)) {
            throw new \InvalidArgumentException("Geçersiz plan: {$plan}");
        }

        $planConfig = self::PLANS[$plan];

        // Mevcut abonelik devam ediyorsa üzerine ekle, yoksa bugünden başlat
        $startDate = $user->hasActiveSubscription()
            ? $user->subscription_ends_at
            : now();

        $user->update([
            'subscription_plan'       => $plan,
            'subscription_started_at' => now(),
            'subscription_ends_at'    => Carbon::parse($startDate)->addMonths($months),
            'offer_limit'             => $planConfig['offer_limit'],
        ]);
    }

    // ── Abonelik iptal etme ───────────────────────────────────

    public function cancel(User $user): void
    {
        // Süre dolunca otomatik free'ye düşsün diye ends_at olduğu gibi kalır
        // Manuel olarak free'ye almak istenirse downgrade kullanılır
        $user->update([
            'subscription_ends_at' => now(), // Hemen sonlandır
        ]);
    }

    // ── Plan düşürme (downgrade) ──────────────────────────────

    public function downgradeToFree(User $user): void
    {
        $user->update([
            'subscription_plan'       => 'free',
            'subscription_started_at' => null,
            'subscription_ends_at'    => null,
            'offer_limit'             => self::PLANS['free']['offer_limit'],
        ]);
    }

    // ── Süresi dolmuş abonelikleri kontrol et ─────────────────
    // Bu metot bir scheduled command ile çağrılır (Modül 12'de)

    public function expireIfNeeded(User $user): bool
    {
        if (
            $user->subscription_plan !== 'free' &&
            $user->subscription_ends_at &&
            $user->subscription_ends_at->isPast()
        ) {
            $this->downgradeToFree($user);
            return true;
        }

        return false;
    }

    // ── Durum özeti ───────────────────────────────────────────

    public function summary(User $user): array
    {
        $plan = $this->getPlan($user->subscription_plan);

        return [
            'plan'              => $user->subscription_plan,
            'plan_label'        => $plan['label'],
            'price'             => $plan['price'],
            'is_active'         => $user->hasActiveSubscription(),
            'started_at'        => $user->subscription_started_at?->format('d.m.Y'),
            'ends_at'           => $user->subscription_ends_at?->format('d.m.Y'),
            'days_remaining'    => $user->subscription_ends_at?->isPast()
                ? 0
                : (int) now()->diffInDays($user->subscription_ends_at),
            'offer_limit'       => $user->offer_limit,
            'offers_used'       => $this->offersUsedThisMonth($user),
            'offers_remaining'  => $user->remainingOffers(),
        ];
    }

    // ── Bu ay kullanılan teklif sayısı ────────────────────────

    public function offersUsedThisMonth(User $user): int
    {
        // Offer modeli Modül 10+'da gelecek, şimdilik 0 dön
        if (!class_exists(\App\Models\Offer::class)) return 0;

        return \App\Models\Offer::where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }
}