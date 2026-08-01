<?php

namespace App\Services\PaymentGateways;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayTR iFrame API entegrasyonu.
 *
 * Akış:
 *  1) getIframeToken() → PayTR'ın "get-token" uç noktasına istek atar,
 *     dönen token ile frontend `https://www.paytr.com/odeme/guvenli/{token}`
 *     adresini bir <iframe> içinde gösterir.
 *  2) Kullanıcı ödemeyi PayTR'ın kendi arayüzünde tamamlar.
 *  3) PayTR, Mağaza Paneli'nde tanımlı "Bildirim URL"sine (bizim tarafta
 *     POST /api/payments/paytr/callback) sunucu-sunucu bir POST isteği
 *     atar; bu isteğin hash'i verifyCallbackHash() ile doğrulanır.
 *
 * ÖNEMLİ: Buradaki hash formülleri PayTR'ın uzun süredir değişmeyen
 * standart iFrame API dokümantasyonuna göre yazılmıştır. Canlıya almadan
 * önce güncel PayTR Entegrasyon Kılavuzu ile birebir teyit edin — gerçek
 * merchant kimlik bilgileri olmadan bu sandbox'ta uçtan uca test edilemedi.
 *
 * @see https://dev.paytr.com/iframe-api
 */
class PayTrGateway
{
    private const TOKEN_ENDPOINT = 'https://www.paytr.com/odeme/api/get-token';
    public const IFRAME_BASE_URL = 'https://www.paytr.com/odeme/guvenli/';

    private ?PaymentGatewaySetting $settings;

    public function __construct()
    {
        $this->settings = PaymentGatewaySetting::forGateway('paytr');
    }

    public function isActive(): bool
    {
        return (bool) ($this->settings?->is_active);
    }

    private function merchantId(): string
    {
        return (string) ($this->settings?->credential('merchant_id') ?? config('services.paytr.merchant_id'));
    }

    private function merchantKey(): string
    {
        return (string) ($this->settings?->credential('merchant_key') ?? config('services.paytr.merchant_key'));
    }

    private function merchantSalt(): string
    {
        return (string) ($this->settings?->credential('merchant_salt') ?? config('services.paytr.merchant_salt'));
    }

    private function isTestMode(): bool
    {
        if ($this->settings) {
            return (bool) $this->settings->is_test_mode;
        }

        return (bool) config('services.paytr.test_mode', true);
    }

    private function okUrl(): string
    {
        return $this->settings?->credential('merchant_ok_url') ?? config('services.paytr.ok_url');
    }

    private function failUrl(): string
    {
        return $this->settings?->credential('merchant_fail_url') ?? config('services.paytr.fail_url');
    }

    /**
     * @param  string  $merchantOid  Benzersiz sipariş kodu (sadece harf/rakam/alt çizgi, bizim tarafta Payment->id'den türetiliyor).
     * @param  int  $amountKurus  Tutar KURUŞ cinsinden (1 TL = 100).
     * @param  array{name:string,email:string,phone:?string,address:?string}  $buyer
     * @param  array<int, array{name:string, price:int, qty:int}>  $basketItems  price KURUŞ cinsinden.
     * @return array{success:bool, token?:string, iframe_url?:string, error?:string}
     */
    public function getIframeToken(
        string $merchantOid,
        int $amountKurus,
        array $buyer,
        array $basketItems,
        string $userIp,
    ): array {
        if (!$this->merchantId() || !$this->merchantKey() || !$this->merchantSalt()) {
            return [
                'success' => false,
                'error'   => 'PayTR kimlik bilgileri tanımlı değil. Admin panelden Ödeme Sağlayıcıları → PayTR altına merchant_id/merchant_key/merchant_salt girin.',
            ];
        }

        $userBasket = base64_encode(json_encode(array_map(
            fn ($item) => [$item['name'], number_format($item['price'] / 100, 2, '.', ''), $item['qty']],
            $basketItems
        )));

        $noInstallment  = 0;
        $maxInstallment = 0;
        $currency       = 'TL';
        $testMode       = $this->isTestMode() ? 1 : 0;

        $hashStr = $this->merchantId() . $userIp . $merchantOid . $buyer['email'] . $amountKurus
            . $userBasket . $noInstallment . $maxInstallment . $currency . $testMode;

        $paytrToken = base64_encode(hash_hmac(
            'sha256',
            $hashStr . $this->merchantSalt(),
            $this->merchantKey(),
            true
        ));

        $payload = [
            'merchant_id'       => $this->merchantId(),
            'user_ip'           => $userIp,
            'merchant_oid'      => $merchantOid,
            'email'             => $buyer['email'],
            'payment_amount'    => $amountKurus,
            'paytr_token'       => $paytrToken,
            'user_basket'       => $userBasket,
            'debug_on'          => $testMode,
            'no_installment'    => $noInstallment,
            'max_installment'   => $maxInstallment,
            'user_name'         => $buyer['name'],
            'user_address'      => $buyer['address'] ?: 'Belirtilmedi',
            'user_phone'        => $buyer['phone'] ?: '05000000000',
            'merchant_ok_url'   => $this->okUrl(),
            'merchant_fail_url' => $this->failUrl(),
            'timeout_limit'     => 30,
            'currency'          => $currency,
            'test_mode'         => $testMode,
            'lang'              => 'tr',
        ];

        try {
            $response = Http::asForm()->post(self::TOKEN_ENDPOINT, $payload);
        } catch (\Throwable $e) {
            Log::error('PayTR get-token isteği başarısız', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'PayTR sunucusuna ulaşılamadı.'];
        }

        $body = $response->json();

        if (!is_array($body) || ($body['status'] ?? null) !== 'success') {
            Log::warning('PayTR get-token reddetti', ['response' => $body, 'http_status' => $response->status()]);
            return [
                'success' => false,
                'error'   => $body['reason'] ?? 'PayTR token alınamadı.',
            ];
        }

        return [
            'success'     => true,
            'token'       => $body['token'],
            'iframe_url'  => self::IFRAME_BASE_URL . $body['token'],
        ];
    }

    /**
     * PayTR'ın bildirim (callback) isteğindeki hash'i doğrular.
     *
     * @param  array  $post  $request->all() — merchant_oid, status, total_amount, hash alanlarını içermeli.
     */
    public function verifyCallbackHash(array $post): bool
    {
        if (!isset($post['merchant_oid'], $post['status'], $post['total_amount'], $post['hash'])) {
            return false;
        }

        $hashStr = $post['merchant_oid'] . $this->merchantSalt() . $post['status'] . $post['total_amount'];
        $expected = base64_encode(hash_hmac('sha256', $hashStr, $this->merchantKey(), true));

        return hash_equals($expected, (string) $post['hash']);
    }
}
