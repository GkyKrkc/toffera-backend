<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * https://teklifmeydani.com/bayilik — bayi sahibi / departman personeli
 * için ayrı, SMS OTP'li giriş. Kayıt akışındaki OtpService'i AYNEN
 * kullanır (yeni bir 'bayilik_login' purpose string'iyle, ayrı bir
 * OTP/deneme sayacı — kayıt/normal giriş OTP'leriyle çakışmaz).
 *
 * TASARIM — neden bu 3 route routes/web.php'de (routes/api.php DEĞİL):
 * verifyOtp() başarılı olduğunda gerçek bir Laravel OTURUMU (session
 * cookie) açması gerekiyor, çünkü hedef `/admin/bayilik` (ayrı Bayilik
 * paneli — bkz. BayilikPanelProvider) Filament paneli auth:sanctum
 * token değil, session tabanlı 'web' guard kullanıyor.
 * routes/api.php'deki grup stateless (Sanctum Bearer token) — session
 * hiç kurulmaz. routes/web.php'ye taşımak `web` middleware grubunu
 * (StartSession/CSRF/Cookie) otomatik uygular. URL path'i yine de
 * "/api/bayilik/..." ile başlıyor ki nginx'in mevcut proxy kuralı
 * (~ ^/(api|admin|livewire)) hiçbir yeni sunucu ayarı gerekmeden bu
 * route'ları Laravel'e yönlendirsin.
 *
 * AKIŞ:
 *   1) GET  /api/bayilik/csrf        → session başlatır, CSRF token döner (frontend fetch ile, credentials:include)
 *   2) POST /api/bayilik/send-otp    → fetch ile, X-CSRF-TOKEN header'ıyla, JSON döner
 *   3) POST /api/bayilik/verify-otp  → GERÇEK native <form> POST (fetch DEĞİL) — başarılıysa
 *      Auth::guard('web')->login() + redirect('/admin/bayilik'), tarayıcı
 *      yönlendirmeyi TAKİP EDER ve session cookie'sini oraya taşır.
 */
class BayilikAuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    private const PURPOSE = 'bayilik_login';

    // ─────────────────────────────────────────────────────────
    // GET /api/bayilik/csrf
    // Auth: Yok — sayfa yüklendiğinde frontend tarafından çağrılır,
    // session'ı başlatır ve native form'a gömülecek CSRF token'ı döner.
    // ─────────────────────────────────────────────────────────
    public function csrf(Request $request): JsonResponse
    {
        return response()->json([
            'csrf_token' => csrf_token(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/bayilik/send-otp
    // Auth: Yok
    // ─────────────────────────────────────────────────────────
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^[0-9]{10,11}$/',
        ], [
            'phone.regex' => 'Geçerli bir telefon numarası girin.',
        ]);

        $user = $this->findBayilikUser($request->phone);

        if (!$user) {
            // Bayi/personel değilse veya böyle bir kullanıcı yoksa AYNI genel
            // mesaj — telefon numarasının sistemde kayıtlı olup olmadığını
            // veya bayilik yetkisi taşıyıp taşımadığını dışarıya sızdırmaz.
            return response()->json([
                'message' => 'Bu telefon numarasına kayıtlı bir bayilik hesabı bulunamadı.',
            ], 404);
        }

        if ($user->is_banned) {
            return response()->json([
                'message' => 'Hesabınız askıya alınmıştır. Detay için genel merkeze başvurun.',
            ], 403);
        }

        if (!$this->otp->canResend($user->phone, self::PURPOSE)) {
            $seconds = $this->otp->secondsUntilResend($user->phone, self::PURPOSE);
            return response()->json([
                'message' => "Lütfen {$seconds} saniye bekleyin.",
                'seconds' => $seconds,
            ], 429);
        }

        $this->otp->sendOtp($user->phone, self::PURPOSE);

        return response()->json([
            'message'   => 'Giriş kodu gönderildi.',
            'debug_otp' => $this->otp->debugCode($user->phone, self::PURPOSE),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/bayilik/verify-otp
    // Auth: Yok — GERÇEK native <form> POST ile çağrılmalı (fetch DEĞİL),
    // çünkü başarı durumunda dönen redirect'in tarayıcı tarafından TAKİP
    // EDİLMESİ ve session cookie'sinin /admin'e taşınması gerekiyor.
    // ─────────────────────────────────────────────────────────
    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^[0-9]{10,11}$/',
            'otp'   => 'required|digits:6',
        ]);

        $user = $this->findBayilikUser($request->phone);

        if (!$user || $user->is_banned) {
            return redirect('/bayilik?error=' . urlencode('Giriş yapılamadı. Bilgilerinizi kontrol edin.'));
        }

        try {
            $this->otp->verifyOtp($user->phone, $request->otp, self::PURPOSE);
        } catch (\Exception $e) {
            return redirect('/bayilik?error=' . urlencode($e->getMessage()));
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        // Ayrı bayilik paneline yönlendir — bkz. BayilikPanelProvider
        // (id 'bayilik', path 'admin/bayilik'). Genel merkez /admin'e
        // DEĞİL: dealer/dealer_staff artık oraya hiç giremiyor
        // (bkz. User::canAccessPanel).
        return redirect('/admin/bayilik');
    }

    // ─────────────────────────────────────────────────────────
    // Private — telefon numarasına ait, bayilik sistemine erişimi olan
    // kullanıcı (dealer VEYA dealer_staff rolü). Diğer roller (buyer/agent/
    // admin) bu portaldan HİÇ giremez — admin zaten /admin'e kendi giriş
    // formundan girer.
    // ─────────────────────────────────────────────────────────
    private function findBayilikUser(string $phone): ?User
    {
        $user = User::where('phone', $phone)->first();

        if (!$user || !($user->hasRole('dealer') || $user->hasRole('dealer_staff'))) {
            return null;
        }

        return $user;
    }
}
