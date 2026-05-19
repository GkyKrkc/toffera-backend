<?php

namespace App\Services;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class TokenService
{
    // Token tipleri — isimler tutarlı olsun
    public const REGISTER_FLOW = 'register-flow'; // Kayıt akışında geçici token
    public const AUTH_TOKEN    = 'auth-token';     // Giriş sonrası kalıcı token

    // ─────────────────────────────────────────────────────────
    // Kayıt akışı için geçici token
    // Adım 1'de verilir, kayıt tamamlanınca silinir
    // ─────────────────────────────────────────────────────────
    public function createRegisterToken(User $user): string
    {
        // Varsa eskiyi sil
        $user->tokens()->where('name', self::REGISTER_FLOW)->delete();

        return $user->createToken(self::REGISTER_FLOW)->plainTextToken;
    }

    // ─────────────────────────────────────────────────────────
    // Giriş / kayıt tamamlama sonrası kalıcı token
    // ─────────────────────────────────────────────────────────
    public function createAuthToken(User $user): string
    {
        // Aynı cihazdaki eski auth token'ı sil (tek oturum politikası)
        $user->tokens()->where('name', self::AUTH_TOKEN)->delete();

        // register-flow token varsa onu da temizle
        $user->tokens()->where('name', self::REGISTER_FLOW)->delete();

        return $user->createToken(self::AUTH_TOKEN)->plainTextToken;
    }

    // ─────────────────────────────────────────────────────────
    // Mevcut cihazdan çıkış
    // ─────────────────────────────────────────────────────────
    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    // ─────────────────────────────────────────────────────────
    // Tüm cihazlardan çıkış
    // ─────────────────────────────────────────────────────────
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    // ─────────────────────────────────────────────────────────
    // Belirli bir token tipini iptal et
    // ─────────────────────────────────────────────────────────
    public function revokeByName(User $user, string $name): void
    {
        $user->tokens()->where('name', $name)->delete();
    }

    // ─────────────────────────────────────────────────────────
    // Kullanıcının kaç aktif token'ı var
    // ─────────────────────────────────────────────────────────
    public function activeTokenCount(User $user): int
    {
        return $user->tokens()->count();
    }

    // ─────────────────────────────────────────────────────────
    // Token tipini kontrol et (mevcut istek için)
    // ─────────────────────────────────────────────────────────
    public function currentTokenIs(User $user, string $name): bool
    {
        return $user->currentAccessToken()?->name === $name;
    }

    // ─────────────────────────────────────────────────────────
    // Kayıt akışı token'ı mı yoksa auth token mı geliyor
    // (Bazı endpoint'lerde sadece kalıcı token kabul edilir)
    // ─────────────────────────────────────────────────────────
    public function isRegisterFlowToken(User $user): bool
    {
        return $this->currentTokenIs($user, self::REGISTER_FLOW);
    }

    public function isAuthToken(User $user): bool
    {
        return $this->currentTokenIs($user, self::AUTH_TOKEN);
    }
}