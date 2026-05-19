<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    | SPA (cookie-based) auth için domain listesi.
    | Mobil uygulama ve harici API istemcileri token kullandığı için
    | bu liste şimdilik boş bırakılabilir; sadece web SPA varsa eklenir.
    */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', implode(',', [
        'localhost',
        'localhost:3000',
        'localhost:5173',
        '127.0.0.1',
        '127.0.0.1:8000',
        parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST),
    ]))),

    /*
    |--------------------------------------------------------------------------
    | Token Guard
    |--------------------------------------------------------------------------
    */
    'guard' => ['sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Token Süreleri
    |--------------------------------------------------------------------------
    | null = süresiz
    | Dakika cinsinden: 60*24 = 1 gün, 60*24*30 = 30 gün
    */
    'expiration' => null, // Token süresini kontrol etmiyoruz, logout ile yönetiyoruz

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    */
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'      => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'  => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];