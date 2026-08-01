<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * payment_gateway_settings tablosu — her ödeme sağlayıcısı (paytr, iyzico)
 * için tek bir satır tutar. Kimlik bilgileri (merchant_id/merchant_key/
 * merchant_salt vb.) "credentials" alanında şifreli JSON olarak saklanır,
 * .env'e yazılmaz — böylece admin panelden değiştirilebilir ve repo'ya
 * sızmaz.
 */
class PaymentGatewaySetting extends Model
{
    protected $fillable = [
        'gateway', 'credentials', 'is_active', 'is_test_mode',
    ];

    protected $casts = [
        'credentials'  => 'encrypted:array',
        'is_active'    => 'boolean',
        'is_test_mode' => 'boolean',
    ];

    public static function forGateway(string $gateway): ?self
    {
        return static::where('gateway', $gateway)->first();
    }

    /**
     * Belirli bir kimlik bilgisi anahtarını okur (ör. "merchant_id").
     * DB'de boşsa (ör. henüz admin panelden girilmemişse) config/services.php
     * içindeki .env fallback'ine düşer — böylece geliştirme ortamında
     * .env ile de çalışabilir, üretimde admin panelden girilen değer
     * önceliklidir.
     */
    public function credential(string $key, mixed $default = null): mixed
    {
        $value = $this->credentials[$key] ?? null;

        return ($value !== null && $value !== '') ? $value : $default;
    }
}
