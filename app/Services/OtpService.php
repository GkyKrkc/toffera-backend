<?php

namespace App\Services;

use App\Models\SmsDispatchLog;
use Illuminate\Support\Facades\Cache;

/**
 * OTP doğrulama servisi — Redis/Cache tabanlı.
 *
 * SmsService.php'nin OTP kısmının (sendOtp/verifyOtp/canResend/
 * secondsUntilResend) YERİNE geçer — AYNI metod imzalarıyla yazıldı,
 * böylece RegisterController/AuthController/PasswordResetController'da
 * TEK satır değişir (constructor'daki tip). Metod gövdelerine dokunmaya
 * gerek yok.
 *
 * SmsService.php SİLİNMEDİ — UserStatusService ve DemandRegionMatcher
 * onu farklı amaçla (OTP dışı bildirim SMS'i) kullanıyor olabilir,
 * o dosyaları görmeden dokunmuyoruz.
 *
 * Neden sms_logs tablosu yerine Redis:
 * - Kod TTL sonunda otomatik silinir, tablo hiç şişmez.
 * - Deneme sayacı da aynı yapıda, ayrı kolon/sorgu gerekmez.
 * - Hassas veri (kod) kalıcı DB'de değil, geçici cache'te durur.
 */
class OtpService
{
    private const TTL_MINUTES   = 5;
    private const MAX_ATTEMPTS  = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(private SmsGatewayService $gateway)
    {
    }

    private function codeKey(string $phone, string $purpose): string
    {
        return "otp:code:{$purpose}:{$phone}";
    }

    private function attemptsKey(string $phone, string $purpose): string
    {
        return "otp:attempts:{$purpose}:{$phone}";
    }

    private function cooldownKey(string $phone, string $purpose): string
    {
        return "otp:cooldown:{$purpose}:{$phone}";
    }

    /**
     * Yeni OTP üretir, Redis'e TTL ile yazar, gönderir, denetim kaydı düşer.
     * SmsService::sendOtp() ile aynı imza (dönüş değeri controller'larda
     * kullanılmıyor, o yüzden void bırakıldı — SmsLog döndürmeye gerek yok).
     */
    public function sendOtp(string $phone, string $purpose = 'register'): void
    {
        if (Cache::has($this->cooldownKey($phone, $purpose))) {
            return; // sessizce yut — controller zaten canResend ile önden kontrol ediyor
        }

        $code = (string) str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->codeKey($phone, $purpose), $code, now()->addMinutes(self::TTL_MINUTES));
        Cache::put(
            $this->cooldownKey($phone, $purpose),
            now()->addSeconds(self::RESEND_COOLDOWN_SECONDS),
            now()->addSeconds(self::RESEND_COOLDOWN_SECONDS)
        );
        Cache::forget($this->attemptsKey($phone, $purpose));

        $message = $this->buildMessage($code, $purpose);
        $result  = $this->gateway->send($phone, $message);

        SmsDispatchLog::create([
            'phone'                => $phone,
            'purpose'              => "otp_{$purpose}",
            'message'              => $message,
            'provider'             => $result['provider'],
            'provider_message_id'  => $result['provider_message_id'],
            'status'               => $result['status'],
            'cost'                 => 0,
        ]);
    }

    /**
     * SmsService::verifyOtp() ile aynı davranış: başarısızsa \Exception fırlatır
     * (kullanıcıya gösterilecek mesajla), başarılıysa true döner.
     *
     * @throws \Exception
     */
    public function verifyOtp(string $phone, string $code, string $purpose): bool
    {
        $realCode = Cache::get($this->codeKey($phone, $purpose));

        if (!$realCode) {
            throw new \Exception('Kod hatalı veya süresi dolmuş.');
        }

        if (!hash_equals($realCode, $code)) {
            $attempts = (int) Cache::increment($this->attemptsKey($phone, $purpose));
            Cache::put($this->attemptsKey($phone, $purpose), $attempts, now()->addMinutes(self::TTL_MINUTES));

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->invalidate($phone, $purpose);
                throw new \Exception('Çok fazla hatalı deneme. Lütfen yeni kod isteyin.');
            }

            $remaining = self::MAX_ATTEMPTS - $attempts;
            throw new \Exception("Kod hatalı. {$remaining} deneme hakkınız kaldı.");
        }

        $this->invalidate($phone, $purpose);
        return true;
    }

    public function canResend(string $phone, string $purpose = 'register'): bool
    {
        return !Cache::has($this->cooldownKey($phone, $purpose));
    }

    /**
     * Test/debug amaçlı — kodu API cevabında göstermek için. GÜVENLİK:
     * SADECE SMS_PROVIDER=log iken bir şey döner, gerçek sağlayıcıya
     * (netgsm/ileti365) geçildiği an otomatik olarak null döner — elle
     * bir yeri kapatmayı unutma riski yok, tek kontrol noktası burası.
     */
    public function debugCode(string $phone, string $purpose): ?string
    {
        if (config('sms.provider') !== 'log') {
            return null;
        }
        return Cache::get($this->codeKey($phone, $purpose));
    }

    public function secondsUntilResend(string $phone, string $purpose = 'register'): int
    {
        $until = Cache::get($this->cooldownKey($phone, $purpose));
        if (!$until) return 0;
        return max(0, (int) now()->diffInSeconds($until));
    }

    private function invalidate(string $phone, string $purpose): void
    {
        Cache::forget($this->codeKey($phone, $purpose));
        Cache::forget($this->attemptsKey($phone, $purpose));
    }

    private function buildMessage(string $code, string $purpose): string
    {
        return match ($purpose) {
            'register'       => "Teklif MEYDANI kayıt kodunuz: {$code} (5 dk geçerli)",
            'login'          => "Teklif MEYDANI giriş kodunuz: {$code} (5 dk geçerli)",
            'password_reset' => "Teklif MEYDANI şifre sıfırlama kodunuz: {$code} (5 dk geçerli)",
            default          => "Teklif MEYDANI doğrulama kodunuz: {$code}",
        };
    }
}
