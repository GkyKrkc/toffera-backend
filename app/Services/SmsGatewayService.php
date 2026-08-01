<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "Bir SMS'i fiilen gönder" işinin TEK yeri burası. OtpService (doğrulama
 * kodları) ve SmsChannel (kritik bildirimler: teklif kabul/red, araç
 * satıldı) ikisi de bu sınıfı çağırır. Sağlayıcı (netgsm/ileti365/log)
 * seçimi tek bir yerde (config/sms.php) — provider değişince burada
 * hiçbir şey değişmez.
 *
 * SmsService.php'deki mevcut sendNetgsm/sendIleti365 mantığı BİREBİR
 * korundu, sadece "OTP'ye özel iş mantığından" (kod üretme, SmsLog'a
 * yazma) ayrıştırıldı — o kısım artık OtpService'in işi.
 */
class SmsGatewayService
{
    /**
     * @return array{status: string, provider: string, provider_message_id: ?string}
     */
    public function send(string $phone, string $message): array
    {
        $provider = config('sms.provider', 'log');

        if ($provider === 'log') {
            Log::channel('daily')->info("[SMS-LOG] → {$phone}: {$message}");
            return ['status' => 'stub_logged', 'provider' => 'log', 'provider_message_id' => null];
        }

        return match ($provider) {
            'netgsm'   => $this->sendNetgsm($phone, $message),
            'ileti365' => $this->sendIleti365($phone, $message),
            default    => $this->fail($provider, "Bilinmeyen SMS provider: {$provider}"),
        };
    }

    private function sendNetgsm(string $phone, string $message): array
    {
        try {
            $response = Http::timeout(10)->get('https://api.netgsm.com.tr/sms/send/get', [
                'usercode'  => config('sms.netgsm.usercode'),
                'password'  => config('sms.netgsm.password'),
                'gsmno'     => $phone,
                'message'   => $message,
                'msgheader' => config('sms.netgsm.header', 'TOFFERA'),
            ]);

            if (!$response->successful()) {
                Log::error('Netgsm SMS gönderilemedi', [
                    'phone' => $phone, 'status' => $response->status(), 'body' => $response->body(),
                ]);
                return $this->fail('netgsm', $response->body());
            }

            return ['status' => 'sent', 'provider' => 'netgsm', 'provider_message_id' => trim($response->body())];
        } catch (\Throwable $e) {
            Log::error('Netgsm bağlantı hatası: ' . $e->getMessage());
            return $this->fail('netgsm', $e->getMessage());
        }
    }

    private function sendIleti365(string $phone, string $message): array
    {
        try {
            $response = Http::timeout(10)
                ->withToken(config('sms.ileti365.token'))
                ->post('https://api.ileti365.com/v1/sms/send', [
                    'to' => $phone, 'message' => $message, 'title' => config('sms.ileti365.title', 'TOFFERA'),
                ]);

            if (!$response->successful()) {
                Log::error('İleti365 SMS gönderilemedi', [
                    'phone' => $phone, 'status' => $response->status(), 'body' => $response->body(),
                ]);
                return $this->fail('ileti365', $response->body());
            }

            return ['status' => 'sent', 'provider' => 'ileti365', 'provider_message_id' => $response->json('id')];
        } catch (\Throwable $e) {
            Log::error('İleti365 bağlantı hatası: ' . $e->getMessage());
            return $this->fail('ileti365', $e->getMessage());
        }
    }

    private function fail(string $provider, string $reason): array
    {
        return ['status' => 'failed', 'provider' => $provider, 'provider_message_id' => null];
    }
}
