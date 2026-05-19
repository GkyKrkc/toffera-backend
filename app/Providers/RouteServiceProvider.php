<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        // ── Genel API limiti ──────────────────────────────────
        // Giriş yapmış → user ID bazlı, yapmamış → IP bazlı
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        // ── OTP gönderimi ─────────────────────────────────────
        // Aynı IP'den dakikada 3, saatte 10 OTP isteği
        RateLimiter::for('otp', function (Request $request) {
            return [
                Limit::perMinute(3)->by($request->ip()),
                Limit::perHour(10)->by($request->ip()),
            ];
        });

        // ── Giriş denemeleri ──────────────────────────────────
        // Aynı telefon + IP kombinasyonu için dakikada 5 deneme
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->input('phone', '') . '|' . $request->ip());
        });

        // ── Dosya yükleme ─────────────────────────────────────
        // Dakikada 10 yükleme
        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->id ?? $request->ip()
            );
        });

        // ── Admin işlemleri ───────────────────────────────────
        // Admin için daha geniş limit
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?? $request->ip());
        });
    }
}