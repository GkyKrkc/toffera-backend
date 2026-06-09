<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DemandController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\AgentRegionController;
use App\Http\Controllers\Api\DemandStatsController;
use App\Http\Controllers\Api\PortfolioController;
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
    Route::get('/subscription/plans',    [SubscriptionController::class, 'plans']);
    Route::get('/categories',            [DemandController::class,       'categories']);
    Route::get('/demands/stats/summary', [DemandStatsController::class,  'summary']);
    Route::get('/demands/stats/cities',  [DemandStatsController::class,  'cities']);
    Route::get('/demands',               [DemandController::class,       'index']);
    Route::get('/demands/{demand}',      [DemandController::class,       'show']);
    Route::get('/car-brands',            [\App\Http\Controllers\Api\CarController::class, 'brands']);
    Route::get('/car-models',            [\App\Http\Controllers\Api\CarController::class, 'models']);
    Route::get('/car-versions',          [\App\Http\Controllers\Api\CarController::class, 'versions']);
});

// ─────────────────────────────────────────────────────────────
// KİMLİK DOĞRULAMALI ENDPOINT'LER
// ─────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'auth.token', 'user.status', 'throttle:api'])->group(function () {

    // ── Oturum ───────────────────────────────────────────────
    Route::post('/logout',     [AuthController::class, 'logout']);
    Route::post('/logout/all', [AuthController::class, 'logoutAll']);
    Route::get('/me',          [AuthController::class, 'me']);

    // ── Profil & Şifre ────────────────────────────────────────
    Route::get('/user/profile',  [UserProfileController::class, 'show']);
    Route::put('/user/profile',  [UserProfileController::class, 'update']);
    Route::put('/user/password', [UserProfileController::class, 'updatePassword']);

    // ── Adres Yönetimi ────────────────────────────────────────
    Route::prefix('user/addresses')->group(function () {
        Route::get('/',                        [UserAddressController::class, 'index']);
        Route::post('/',                       [UserAddressController::class, 'store']);
        Route::put('/{address}',               [UserAddressController::class, 'update']);
        Route::delete('/{address}',            [UserAddressController::class, 'destroy']);
        Route::patch('/{address}/set-default', [UserAddressController::class, 'setDefault']);
    });

    // ── Abonelik ──────────────────────────────────────────────
    Route::get('/subscription',           [SubscriptionController::class, 'show']);
    Route::post('/subscription/activate', [SubscriptionController::class, 'activate'])
        ->middleware('agent.approved');
    Route::post('/subscription/cancel',   [SubscriptionController::class, 'cancel'])
        ->middleware('agent.approved');

    Route::middleware('phone.verified')->group(function () {

        // ── MÜŞTERİ ──────────────────────────────────────────
        Route::middleware('role:buyer')->prefix('buyer')->group(function () {
            Route::get('/demands',                  [DemandController::class, 'myDemands']);
            Route::post('/demands',                 [DemandController::class, 'store']);
            Route::post('/demands/{demand}/cancel', [DemandController::class, 'cancel']);

            Route::get('/demands/{demand}/offers',  [OfferController::class, 'demandOffers']);
            Route::post('/offers/{offer}/accept',   [OfferController::class, 'accept']);
            Route::post('/offers/{offer}/reject',   [OfferController::class, 'reject']);
        });

        // ── UZMAN ─────────────────────────────────────────────
        Route::middleware('agent.approved')->prefix('agent')->group(function () {

            // Teklif
            Route::post('/demands/{demand}/offers', [OfferController::class, 'store'])
                ->middleware('offer.limit');
            Route::get('/offers',                   [OfferController::class, 'myOffers']);
            Route::post('/offers/{offer}/cancel',   [OfferController::class, 'cancel']);

            // Bölge Takibi
            Route::prefix('regions')->group(function () {
                Route::get('/',                      [AgentRegionController::class, 'index']);
                Route::post('/',                     [AgentRegionController::class, 'store']);
                Route::delete('/{region}',           [AgentRegionController::class, 'destroy']);
                Route::patch('/{region}/toggle',     [AgentRegionController::class, 'toggle']);
            });

            // Portföy
            Route::prefix('portfolio')->group(function () {
                Route::get('/',          [PortfolioController::class, 'index']);
                Route::get('/stats',     [PortfolioController::class, 'stats']);
                Route::post('/',         [PortfolioController::class, 'store']);
                Route::put('/{item}',    [PortfolioController::class, 'update']);
                Route::delete('/{item}', [PortfolioController::class, 'destroy']);
            });
        });

        // ── ADMİN ─────────────────────────────────────────────
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

// ─────────────────────────────────────────────────────────────
// REVERB BROADCAST AUTH
// ─────────────────────────────────────────────────────────────
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    $request->headers->set('Accept', 'application/json');
    return \Illuminate\Support\Facades\Broadcast::auth($request);
})->middleware(['auth:sanctum']);
