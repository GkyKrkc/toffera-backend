<?php

use App\Http\Controllers\Api\BayilikAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ─────────────────────────────────────────────────────────────
// BAYİLİK PORTALI — SMS OTP GİRİŞ (teklifmeydani.com/bayilik)
// Bilerek routes/web.php'de (routes/api.php DEĞİL): 'web' middleware
// grubu (session/CSRF) gerekiyor, çünkü başarılı giriş Filament'in
// session tabanlı 'web' guard'ına bir oturum açıyor. URL path'i yine
// de "/api/bayilik/..." — nginx'in mevcut proxy kuralı yeni bir sunucu
// ayarı gerektirmeden bu route'ları Laravel'e yönlendiriyor. Detaylı
// akış açıklaması: app/Http/Controllers/Api/BayilikAuthController.php
// ─────────────────────────────────────────────────────────────
Route::prefix('api/bayilik')->group(function () {
    Route::get('/csrf', [BayilikAuthController::class, 'csrf']);

    Route::middleware('throttle:otp')->group(function () {
        Route::post('/send-otp', [BayilikAuthController::class, 'sendOtp']);
    });

    Route::middleware('throttle:login')->group(function () {
        Route::post('/verify-otp', [BayilikAuthController::class, 'verifyOtp']);
    });
});
