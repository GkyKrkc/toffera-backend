<?php

return [

    'name' => env('APP_NAME', 'TOFFERA'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Frontend URL
    |--------------------------------------------------------------------------
    | VerificationMail ve EmailService'de kullanılır.
    | Email doğrulama linkini oluştururken frontend adresi gerekli.
    | Örn: https://toffera.com veya http://localhost:5173
    */
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => env('APP_TIMEZONE', 'Europe/Istanbul'),

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    */
    'locale'          => env('APP_LOCALE', 'tr'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'tr'),
    'faker_locale'    => env('APP_FAKER_LOCALE', 'tr_TR'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store'  => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];