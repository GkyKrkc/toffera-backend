<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreditPackController;
use App\Http\Controllers\Api\DealerApplicationController;
use App\Http\Controllers\Api\DemandController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\AgentRegionController;
use App\Http\Controllers\Api\DemandStatsController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\QuickMessageController;
use App\Http\Controllers\Api\LegalDocumentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;

// ─────────────────────────────────────────────────────────────
// KAYIT AKIŞI
// ─────────────────────────────────────────────────────────────
Route::middleware('throttle:otp')->group(function () {
    Route::post('/register',          [RegisterController::class, 'register']);
    Route::post('/login/send-otp',    [AuthController::class,     'sendLoginOtp']);
    Route::post('/password/send-otp', [PasswordResetController::class, 'sendResetOtp']);
});

// Kayıt formunda "hesap türü" seçim listesi — auth gerektirmez, register-flow
// başlamadan (ADIM 3'e gelmeden) önce de gösterilebilsin diye throttle:otp
// dışında, genel throttle:api ile sınırlı.
Route::middleware('throttle:api')->group(function () {
    Route::get('/register/account-type-groups', [RegisterController::class, 'accountTypeGroups']);

    // Yasal metinler — kayıt formu (henüz kullanıcı yokken) ve herkese
    // açık "yasal metinler" sayfası için, auth GEREKTİRMEZ.
    Route::get('/legal-documents', [LegalDocumentController::class, 'index']);
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
    Route::get('/credit-packs/plans',    [CreditPackController::class,   'plans']);
    Route::get('/payment-methods',       [PaymentController::class,      'paymentMethods']);
    Route::get('/bank-accounts',         [PaymentController::class,      'bankAccounts']);
    Route::get('/categories',            [DemandController::class,       'categories']);
    Route::get('/demands/stats/summary', [DemandStatsController::class,  'summary']);
    Route::get('/demands/stats/cities',  [DemandStatsController::class,  'cities']);
    Route::get('/demands',               [DemandController::class,       'index']);
    Route::get('/demands/{demand}',      [DemandController::class,       'show']);
    Route::get('/car-brands',            [\App\Http\Controllers\Api\CarController::class, 'brands']);
    Route::get('/car-models',            [\App\Http\Controllers\Api\CarController::class, 'models']);
    Route::get('/car-versions',          [\App\Http\Controllers\Api\CarController::class, 'versions']);

    // Public portföy vitrini
    Route::get('/portfolio/featured',    [PortfolioController::class, 'featured']);
    // Public ilan detayı (modal galerisi için tüm fotoğraflar) — /agent/portfolio/{item}
    // ile karışmasın diye ayrı bir metotta (showPublic), sadece yayındaki ilanlar için.
    Route::get('/portfolio/{item}',      [PortfolioController::class, 'showPublic']);

    // PayTR bildirim (callback) uç noktası — PayTR sunucu-sunucu POST atar,
    // ne auth token ne CSRF taşır, bu yüzden herkese açık grupta. Mağaza
    // Paneli'ndeki "Bildirim URL" alanına bu adres tanımlanmalı.
    Route::post('/payments/paytr/callback', [PaymentController::class, 'paytrCallback']);
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

    // ── Bildirim Tercihleri (SMS/e-posta, kategori bazlı) ────
    Route::get('/user/notification-preferences', [UserProfileController::class, 'notificationPreferences']);
    Route::put('/user/notification-preferences', [UserProfileController::class, 'updateNotificationPreferences']);

    // ── Yasal metin onayları ──────────────────────────────────
    Route::get ('/user/legal-consents', [LegalDocumentController::class, 'myConsents']);
    Route::post('/user/legal-consents', [LegalDocumentController::class, 'acceptConsents']);

    // ── Bayilik Başvurusu — herhangi bir giriş yapmış kullanıcı ──
    Route::get ('/dealer-applications/me', [DealerApplicationController::class, 'me']);
    Route::post('/dealer-applications',    [DealerApplicationController::class, 'store']);

    // Bildirimler
    Route::get   ('/notifications',              [NotificationController::class, 'index']);
    Route::get   ('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post  ('/notifications/{id}/read',    [NotificationController::class, 'markRead']);
    Route::post  ('/notifications/read-all',     [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{id}',         [NotificationController::class, 'destroy']);

    // ── Adres Yönetimi ────────────────────────────────────────
    Route::prefix('user/addresses')->group(function () {
        Route::get('/',                        [UserAddressController::class, 'index']);
        Route::post('/',                       [UserAddressController::class, 'store']);
        Route::put('/{address}',               [UserAddressController::class, 'update']);
        Route::delete('/{address}',            [UserAddressController::class, 'destroy']);
        Route::patch('/{address}/set-default', [UserAddressController::class, 'setDefault']);
    });

    // ── Abonelik & Ödeme ──────────────────────────────────────
    Route::get('/subscription',            [SubscriptionController::class, 'show']);
    // Eski '/subscription/activate' (ödeme almadan direkt aktif eden) kaldırıldı —
    // artık gerçek para akışı PayTR üzerinden: checkout token alır, kullanıcı
    // PayTR iframe'inde öder, PayTR callback'i (yukarıdaki public route) abonelği
    // gerçekten aktif eder.
    Route::post('/subscription/checkout', [PaymentController::class, 'checkout'])
        ->middleware('agent.approved');
    Route::post('/subscription/cancel',   [SubscriptionController::class, 'cancel'])
        ->middleware('agent.approved');

    // Kontör paketi satın alma — bireysel/uzman olmayan kullanıcılar için,
    // agent.approved GEREKMEZ, sadece giriş yapmış olmak yeterli.
    Route::post('/credit-packs/checkout', [PaymentController::class, 'creditPackCheckout']);

    Route::middleware('phone.verified')->group(function () {

        // ── PORTFÖY (Bireysel + Uzman ortak — grup/kategori limitli) ───
        // /agent/portfolio/* rotalarına (aşağıda) dokunulmadı, o hâlâ
        // sadece onaylı uzmanlar için limitsiz çalışıyor. Bu YENİ, ek bir
        // giriş noktası — hem buyer hem agent rolündeki kullanıcılar,
        // account_type_group'una tanımlı kategori+limit'e göre kullanabilir.
        //
        // NOT: prefix bilerek "portfolio" değil "my-portfolio" — üstteki
        // herkese açık GET /portfolio/{item} (showPublic) route'u ile
        // path çakışmasın diye (Laravel route'ları tanım sırasına göre
        // eşleştiriyor, /portfolio/available-categories isteği o wildcard'a
        // düşerdi).
        Route::prefix('my-portfolio')->group(function () {
            // Sabit path'ler {item} wildcard'ından ÖNCE tanımlanmalı,
            // aksi halde "available-categories" bir item ID'si sanılıp
            // ModelNotFound 404'üne düşer.
            Route::get   ('/available-categories', [PortfolioController::class, 'availableCategories']);
            Route::get   ('/',                     [PortfolioController::class, 'index']);
            Route::post  ('/',                     [PortfolioController::class, 'store']);
            Route::get   ('/{item}',               [PortfolioController::class, 'show']);
            Route::put   ('/{item}',               [PortfolioController::class, 'update']);
            Route::delete('/{item}',               [PortfolioController::class, 'destroy']);
        });

        // ── MESAJLAŞMA ────────────────────────────────────────
        // Rol-özel (buyer/agent) prefix'lerin DIŞINDA bilerek: bir konuşmanın
        // agent_id tarafındaki kullanıcı buraya "role:buyer" gibi bir
        // middleware'e takılmadan erişebilsin diye. Yetki kontrolü
        // ConversationController içinde (isParticipant) yapılıyor — bkz.
        // OfferController::show()'daki isOwner/isAgent deseninin aynısı.
        // "Görüşme Başlat" SADECE talep sahibi tarafından çağrılabilir
        // (start() metodu içinde ayrıca kontrol ediliyor).
        Route::prefix('conversations')->group(function () {
            Route::get ('/',                       [ConversationController::class, 'index']);
            Route::get ('/unread-count',            [ConversationController::class, 'unreadCount']);
            Route::post('/offers/{offer}',          [ConversationController::class, 'start']);
            Route::get ('/{conversation}/messages', [ConversationController::class, 'messages']);
            Route::post('/{conversation}/messages', [ConversationController::class, 'sendMessage']);
            Route::post('/{conversation}/read',     [ConversationController::class, 'markRead']);
        });

        Route::get('/quick-messages', [QuickMessageController::class, 'index']);

        // ── MÜŞTERİ ──────────────────────────────────────────
        Route::middleware('role:buyer')->prefix('buyer')->group(function () {
            Route::get('/demands',                  [DemandController::class, 'myDemands']);
            Route::post('/demands',                 [DemandController::class, 'store']);
            Route::post('/demands/{demand}/cancel', [DemandController::class, 'cancel']);

            Route::get('/demands/{demand}/offers',  [OfferController::class, 'demandOffers']);

            Route::get('/offers/{offer}',           [OfferController::class, 'show']);      // ← teklif detay
            Route::post('/offers/{offer}/accept',   [OfferController::class, 'accept']);
            Route::post('/offers/{offer}/reject',   [OfferController::class, 'reject']);
            Route::post('/offers/{offer}/review',   [OfferController::class, 'review']);    // ← "Değerlendiriliyor" işaretleme
            Route::post('/offers/{offer}/favorite',        [OfferController::class, 'toggleFavorite']);
            Route::post('/offers/{offer}/reveal-contact',  [OfferController::class, 'revealContact']); // ← kabul öncesi erken iletişim paylaşımı
            Route::post('/offers/{offer}/confirm-sale',    [OfferController::class, 'confirmSale']);   // ← satış tamamlandı onayı (kesin)

        });

        // ── UZMAN ─────────────────────────────────────────────
        Route::middleware('agent.approved')->prefix('agent')->group(function () {

            // Teklif
            Route::post('/demands/{demand}/offers', [OfferController::class, 'store']);
            Route::get('/offers',                   [OfferController::class, 'myOffers']);
            Route::get('/offers/{offer}',           [OfferController::class, 'show']);    // ← kendi teklif detayı
            Route::post('/offers/{offer}/cancel',   [OfferController::class, 'cancel']);
            Route::post('/offers/{offer}/withdraw', [OfferController::class, 'withdraw']); // ← kabul edilmiş teklifi geri çek

            // Teklif güncelleme — pending veya withdrawn durumdayken
            Route::put('/offers/{offer}', [OfferController::class, 'update']);
            // Modal için: bu talebe uygun (marka/model eşleşen) portföyüm
            Route::get('/demands/{demand}/matching-portfolio', [OfferController::class, 'matchingPortfolio']);

            // Bölge Takibi
            Route::prefix('regions')->group(function () {
                Route::get('/',                  [AgentRegionController::class, 'index']);
                Route::post('/',                 [AgentRegionController::class, 'store']);
                Route::delete('/{region}',       [AgentRegionController::class, 'destroy']);
                Route::patch('/{region}/toggle', [AgentRegionController::class, 'toggle']);
            });

            // Portföy
            Route::prefix('portfolio')->group(function () {
                Route::get   ('/',                          [PortfolioController::class, 'index']);
                Route::post  ('/',                          [PortfolioController::class, 'store']);
                Route::get   ('/stats',                     [PortfolioController::class, 'stats']);
                Route::get   ('/{item}',                    [PortfolioController::class, 'show']);
                Route::put   ('/{item}',                    [PortfolioController::class, 'update']);
                Route::delete('/{item}',                    [PortfolioController::class, 'destroy']);

                // Images
                Route::post  ('/{item}/images',                   [PortfolioController::class, 'uploadImages']);
                Route::post  ('/{item}/images/bulk-delete',       [PortfolioController::class, 'bulkDeleteImages']);
                Route::post  ('/{item}/images/reorder',           [PortfolioController::class, 'reorderImages']);
                Route::delete('/{item}/images/{image}',           [PortfolioController::class, 'deleteImage']);
                Route::patch ('/{item}/images/{image}/cover',     [PortfolioController::class, 'setCover']);

                // Documents
                Route::post  ('/{item}/documents',                [PortfolioController::class, 'uploadDocuments']);
                Route::post  ('/{item}/documents/bulk-delete',    [PortfolioController::class, 'bulkDeleteDocuments']);
                Route::delete('/{item}/documents/{document}',     [PortfolioController::class, 'deleteDocument']);
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
