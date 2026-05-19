<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Provider
    |--------------------------------------------------------------------------
    | Desteklenen: "netgsm" | "ileti365"
    | Local/testing ortamında gönderim yapılmaz, sadece log'lanır.
    */
    'provider' => env('SMS_PROVIDER', 'netgsm'),

    // ── Netgsm ───────────────────────────────────────────────
    'netgsm' => [
        'usercode' => env('NETGSM_USERCODE'),
        'password' => env('NETGSM_PASSWORD'),
        'header'   => env('NETGSM_HEADER', 'TOFFERA'),
    ],

    // ── İleti365 ─────────────────────────────────────────────
    'ileti365' => [
        'token' => env('ILETI365_TOKEN'),
        'title' => env('ILETI365_TITLE', 'TOFFERA'),
    ],

];