<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private SmsService $sms) {}

    // ─────────────────────────────────────────────────────────
    // SMS İLE GİRİŞ — ADIM 1
    // POST /api/login/send-otp
    // Auth: Yok
    // ─────────────────────────────────────────────────────────
    public function sendLoginOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^[0-9]{10,11}$/',
        ], [
            'phone.regex' => 'Geçerli bir telefon numarası girin.',
        ]);

        $user = User::where('phone', $request->phone)->first();

        // Kullanıcı bulunamadıysa genel mesaj ver (güvenlik)
        if (!$user) {
            return response()->json([
                'message' => 'Bu telefon numarasına kayıtlı hesap bulunamadı.',
            ], 404);
        }

        // Hesap durumu kontrolü
        $this->checkAccountStatus($user);

        if (!$this->sms->canResend($user->phone, 'login')) {
            $seconds = $this->sms->secondsUntilResend($user->phone, 'login');
            return response()->json([
                'message' => "Lütfen {$seconds} saniye bekleyin.",
                'seconds' => $seconds,
            ], 429);
        }

        $this->sms->sendOtp($user->phone, 'login');

        return response()->json([
            'message' => 'Giriş kodu gönderildi.',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // SMS İLE GİRİŞ — ADIM 2
    // POST /api/login/verify-otp
    // Auth: Yok
    // ─────────────────────────────────────────────────────────
    public function verifyLoginOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^[0-9]{10,11}$/',
            'otp'   => 'required|digits:6',
        ], [
            'phone.regex' => 'Geçerli bir telefon numarası girin.',
            'otp.digits'  => '6 haneli giriş kodunu girin.',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'phone' => 'Bu telefon numarasına kayıtlı hesap bulunamadı.',
            ]);
        }

        $this->checkAccountStatus($user);

        try {
            $this->sms->verifyOtp($user->phone, $request->otp, 'login');
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['otp' => $e->getMessage()]);
        }

        // Eski token'ları temizle, yeni token üret
        $user->tokens()->where('name', 'auth-token')->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Giriş başarılı.',
            'token'   => $token,
            'user'    => $this->userResponse($user),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ŞİFRE İLE GİRİŞ
    // POST /api/login
    // Auth: Yok
    // ─────────────────────────────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'phone'    => 'required|string|regex:/^[0-9]{10,11}$/',
            'password' => 'required|string',
        ], [
            'phone.regex' => 'Geçerli bir telefon numarası girin.',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !$user->password) {
            throw ValidationException::withMessages([
                'phone' => 'Telefon numarası veya şifre hatalı.',
            ]);
        }

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Telefon numarası veya şifre hatalı.',
            ]);
        }

        $this->checkAccountStatus($user);

        // Eski token'ları temizle, yeni token üret
        $user->tokens()->where('name', 'auth-token')->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Giriş başarılı.',
            'token'   => $token,
            'user'    => $this->userResponse($user),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ÇIKIŞ
    // POST /api/logout
    // Auth: auth-token
    // ─────────────────────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        // Sadece mevcut cihazın token'ını sil
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Çıkış yapıldı.']);
    }

    // ─────────────────────────────────────────────────────────
    // TÜM CİHAZLARDAN ÇIKIŞ
    // POST /api/logout/all
    // Auth: auth-token
    // ─────────────────────────────────────────────────────────
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Tüm cihazlardan çıkış yapıldı.']);
    }

    // ─────────────────────────────────────────────────────────
    // MEVCUT KULLANICI BİLGİSİ
    // GET /api/me
    // Auth: auth-token
    // ─────────────────────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('agentDocuments');

        return response()->json([
            'user' => array_merge($this->userResponse($user), [
                'offer_limit'         => $user->offer_limit,
                'remaining_offers'    => $user->remainingOffers(),
                'subscription_plan'   => $user->subscription_plan,
                'subscription_ends_at'=> $user->subscription_ends_at?->toDateString(),
                'has_active_subscription' => $user->hasActiveSubscription(),
                'agent_documents'     => $user->agentDocuments->map(fn($d) => [
                    'type'          => $d->document_type,
                    'type_label'    => $d->type_label,
                    'original_name' => $d->original_name,
                    'file_size'     => $d->file_size_human,
                    'uploaded_at'   => $d->created_at->format('d.m.Y'),
                ]),
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Private — Hesap durumu kontrolü
    // ─────────────────────────────────────────────────────────
    private function checkAccountStatus(User $user): void
    {
        if ($user->is_banned) {
            throw ValidationException::withMessages([
                'phone' => 'Hesabınız askıya alınmıştır. Detay için destek hattına başvurun.',
            ]);
        }

        // Uzman pending ise girişe izin ver ama frontend durumu göstersin
        // Rejected ise giriş engellenir
        if ($user->status === 'rejected') {
            throw ValidationException::withMessages([
                'phone' => 'Başvurunuz reddedilmiştir. Detay için destek hattına başvurun.',
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Private — Kullanıcı yanıt verisi
    // ─────────────────────────────────────────────────────────
    private function userResponse(User $user): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'phone'        => $user->phone,
            'email'        => $user->email,
            'status'       => $user->status,
            'agent_type'   => $user->agent_type,
            'company_name' => $user->company_name,
            'roles'        => $user->getRoleNames(),
            'permissions'  => $user->getAllPermissions()->pluck('name'),
        ];
    }
}