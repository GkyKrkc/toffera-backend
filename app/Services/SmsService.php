<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    // ── OTP Oluştur & Gönder ─────────────────────────────────

    public function sendOtp(string $phone, string $purpose = 'register'): SmsLog
    {
        // Aynı telefon + amaç için bekleyen geçerli kod varsa iptal et
        SmsLog::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->delete();

        $code = $this->generateCode();

        $log = SmsLog::create([
            'phone'      => $phone,
            'code'       => $code,
            'purpose'    => $purpose,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->dispatch($phone, $code, $purpose);

        return $log;
    }

    // ── OTP Doğrula ──────────────────────────────────────────

    /**
     * @throws \Exception Kod hatalı, süresi dolmuş veya bloke
     */
    public function verifyOtp(string $phone, string $code, string $purpose): bool
    {
        $log = SmsLog::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$log) {
            throw new \Exception('Kod hatalı veya süresi dolmuş.');
        }

        // Brute-force: 5 yanlış denemeden sonra kodu kilitle
        if ($log->isBlocked()) {
            $log->delete();
            throw new \Exception('Çok fazla hatalı deneme. Lütfen yeni kod isteyin.');
        }

        if ($log->code !== $code) {
            $log->incrementAttempt();
            $remaining = 5 - $log->fresh()->attempt_count;
            throw new \Exception("Kod hatalı. {$remaining} deneme hakkınız kaldı.");
        }

        $log->markAsUsed();

        return true;
    }

    // ── Cooldown kontrolü ─────────────────────────────────────

    public function canResend(string $phone, string $purpose = 'register'): bool
    {
        return !SmsLog::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('created_at', '>', now()->subMinute())
            ->exists();
    }

    public function secondsUntilResend(string $phone, string $purpose = 'register'): int
    {
        $latest = SmsLog::where('phone', $phone)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if (!$latest) return 0;

        $seconds = 60 - now()->diffInSeconds($latest->created_at);
        return max(0, (int) $seconds);
    }

    // ── Private: Kod üret ─────────────────────────────────────

    private function generateCode(): string
    {
        return (string) str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // ── Private: SMS gönder (provider seçimi) ─────────────────

    private function dispatch(string $phone, string $code, string $purpose): void
    {
        $message = $this->buildMessage($code, $purpose);

        // Local/test ortamında sadece log'la, gerçekten gönderme
        if (app()->environment(['local', 'testing'])) {
            Log::channel('daily')->info("SMS OTP [{$purpose}] → {$phone} : {$code}");
            return;
        }

        $provider = config('sms.provider', 'netgsm');

        match ($provider) {
            'netgsm'   => $this->sendNetgsm($phone, $message),
            'ileti365' => $this->sendIleti365($phone, $message),
            default    => Log::error("Bilinmeyen SMS provider: {$provider}"),
        };
    }

    private function buildMessage(string $code, string $purpose): string
    {
        return match ($purpose) {
            'register'       => "TOFFERA kayıt kodunuz: {$code} (5 dk geçerli)",
            'login'          => "TOFFERA giriş kodunuz: {$code} (5 dk geçerli)",
            'password_reset' => "TOFFERA şifre sıfırlama kodunuz: {$code} (5 dk geçerli)",
            default          => "TOFFERA doğrulama kodunuz: {$code}",
        };
    }

    // ── Netgsm entegrasyonu ───────────────────────────────────

    private function sendNetgsm(string $phone, string $message): void
    {
        try {
            $response = Http::timeout(10)->get('https://api.netgsm.com.tr/sms/send/get', [
                'usercode' => config('sms.netgsm.usercode'),
                'password' => config('sms.netgsm.password'),
                'gsmno'    => $phone,
                'message'  => $message,
                'msgheader'=> config('sms.netgsm.header', 'TOFFERA'),
            ]);

            if (!$response->successful()) {
                Log::error('Netgsm SMS gönderilemedi', [
                    'phone'  => $phone,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Netgsm bağlantı hatası: ' . $e->getMessage());
        }
    }

    // ── İleti365 entegrasyonu ─────────────────────────────────

    private function sendIleti365(string $phone, string $message): void
    {
        try {
            $response = Http::timeout(10)
                ->withToken(config('sms.ileti365.token'))
                ->post('https://api.ileti365.com/v1/sms/send', [
                    'to'      => $phone,
                    'message' => $message,
                    'title'   => config('sms.ileti365.title', 'TOFFERA'),
                ]);

            if (!$response->successful()) {
                Log::error('İleti365 SMS gönderilemedi', [
                    'phone'  => $phone,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('İleti365 bağlantı hatası: ' . $e->getMessage());
        }
    }
}