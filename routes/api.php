<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DemandController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────
// KAYIT AKIŞI
// ─────────────────────────────────────────────────────────────
Route::middleware('throttle:otp')->group(function () {
    Route::post('/register',          [RegisterController::class, 'register']);
    Route::post('/login/send-otp',    [AuthController::class,     'sendLoginOtp']);
    Route::post('/password/send-otp', [PasswordResetController::class, 'sendResetOtp']);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/register/verify-otp',       [RegisterController::class, 'verifyOtp']);
    Route::post('/register/resend-otp',       [RegisterController::class, 'resendOtp']);
    Route::post('/register/set-type',         [RegisterController::class, 'setAccountType']);
    Route::post('/register/upload-documents', [RegisterController::class, 'uploadDocuments'])
        ->middleware('throttle:upload');
});

// ─────────────────────────────────────────────────────────────
// GİRİŞ & ŞİFRE SIFIRLAMA
// ─────────────────────────────────────────────────────────────
Route::middleware('throttle:login')->group(function () {
    Route::post('/login',            [AuthController::class,          'login']);
    Route::post('/login/verify-otp', [AuthController::class,          'verifyLoginOtp']);
    Route::post('/password/reset',   [PasswordResetController::class, 'resetPassword']);
});

// ─────────────────────────────────────────────────────────────
// HERKESE AÇIK
// ─────────────────────────────────────────────────────────────
Route::middleware('throttle:api')->group(function () {
    Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);
    Route::get('/categories',         [DemandController::class,        'categories']);
    Route::get('/demands',            [DemandController::class,        'index']);
    Route::get('/demands/{demand}',   [DemandController::class,        'show']);
});

// ─────────────────────────────────────────────────────────────
// KİMLİK DOĞRULAMALI ENDPOINT'LER
// ─────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'auth.token', 'user.status', 'throttle:api'])->group(function () {

    // Oturum
    Route::post('/logout',     [AuthController::class, 'logout']);
    Route::post('/logout/all', [AuthController::class, 'logoutAll']);
    Route::get('/me',          [AuthController::class, 'me']);

    // Abonelik
    Route::get('/subscription',           [SubscriptionController::class, 'show']);
    Route::post('/subscription/activate', [SubscriptionController::class, 'activate'])
        ->middleware('agent.approved');
    Route::post('/subscription/cancel',   [SubscriptionController::class, 'cancel'])
        ->middleware('agent.approved');

    Route::middleware('phone.verified')->group(function () {

        // ── MÜŞTERİ ─────────────────────────────────────────
        Route::middleware('role:buyer')->prefix('buyer')->group(function () {
            // Talep yönetimi
            Route::get('/demands',                        [DemandController::class, 'myDemands']);
            Route::post('/demands',                       [DemandController::class, 'store']);
            Route::post('/demands/{demand}/cancel',       [DemandController::class, 'cancel']);

            // Teklif yönetimi (müşteri tarafı)
            Route::get('/demands/{demand}/offers',        [OfferController::class, 'demandOffers']);
            Route::post('/offers/{offer}/accept',         [OfferController::class, 'accept']);
            Route::post('/offers/{offer}/reject',         [OfferController::class, 'reject']);
        });

        // ── UZMAN ────────────────────────────────────────────
        Route::middleware('agent.approved')->prefix('agent')->group(function () {
            // Teklif verme
            Route::post('/demands/{demand}/offers',       [OfferController::class, 'store'])
                ->middleware('offer.limit');
            Route::get('/offers',                         [OfferController::class, 'myOffers']);
        });

        // ── ADMİN ────────────────────────────────────────────
        Route::middleware(['role:admin', 'throttle:admin'])->prefix('admin')->group(function () {
            Route::get('/users',                      [AdminController::class, 'users']);
            Route::get('/users/{user}',               [AdminController::class, 'showUser']);
            Route::post('/users/{user}/ban',          [AdminController::class, 'banUser']);
            Route::post('/users/{user}/unban',        [AdminController::class, 'unbanUser']);
            Route::post('/users/{user}/subscription', [AdminController::class, 'setSubscription']);
            Route::get('/agents/pending',             [AdminController::class, 'pendingAgents']);
            Route::post('/agents/{user}/approve',     [AdminController::class, 'approveAgent']);
            Route::post('/agents/{user}/reject',      [AdminController::class, 'rejectAgent']);
        });
    });
});