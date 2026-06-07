<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(private SmsService $sms) {}

    // ─────────────────────────────────────────────────────────
    // ADIM 1 — Sıfırlama kodu gönder
    // POST /api/password/send-otp
    // Auth: Yok
    // ─────────────────────────────────────────────────────────
    public function sendResetOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^[0-9]{10,11}$/',
        ]);

        $user = User::where('phone', $request->phone)->first();

        // Kullanıcı yoksa da "gönderildi" de — enumeration saldırısı önlemi
        if (!$user || $user->is_banned) {
            return response()->json([
                'message' => 'Kod gönderildi (kayıtlı hesap varsa).',
            ]);
        }

        if (!$this->sms->canResend($user->phone, 'password_reset')) {
            $seconds = $this->sms->secondsUntilResend($user->phone, 'password_reset');
            return response()->json([
                'message' => "Lütfen {$seconds} saniye bekleyin.",
                'seconds' => $seconds,
            ], 429);
        }

        $this->sms->sendOtp($user->phone, 'password_reset');

        return response()->json([
            'message' => 'Şifre sıfırlama kodu gönderildi.',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ADIM 2 — Kodu doğrula + yeni şifre belirle
    // POST /api/password/reset
    // Auth: Yok
    // ─────────────────────────────────────────────────────────
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'phone'                 => 'required|string|regex:/^[0-9]{10,11}$/',
            'otp'                   => 'required|digits:6',
            'password'              => 'required|string|min:6|confirmed',
        ], [
            'password.min'       => 'Şifre en az 6 karakter olmalıdır.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'phone' => 'Bu telefon numarasına kayıtlı hesap bulunamadı.',
            ]);
        }

        try {
            $this->sms->verifyOtp($user->phone, $request->otp, 'password_reset');
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['otp' => $e->getMessage()]);
        }

        // Şifreyi güncelle, tüm oturumları kapat (güvenlik)
        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.',
        ]);
    }
}