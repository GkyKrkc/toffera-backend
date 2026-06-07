<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentDocument;
use App\Models\User;
use App\Services\EmailService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __construct(
        private SmsService   $sms,
        private EmailService $email,
    ) {}

    // ─────────────────────────────────────────────────────────
    // ADIM 1 — Temel bilgiler + OTP gönder
    // POST /api/register
    // Auth: Yok
    // ─────────────────────────────────────────────────────────
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|regex:/^[0-9]{10,11}$/|unique:users,phone',
            'password' => 'nullable|string|min:6|confirmed',
            'email'    => 'nullable|email|max:255|unique:users,email',
        ], [
            'phone.unique'       => 'Bu telefon numarası zaten kayıtlı.',
            'phone.regex'        => 'Geçerli bir telefon numarası girin (10-11 hane).',
            'email.unique'       => 'Bu e-posta adresi zaten kullanılıyor.',
            'password.min'       => 'Şifre en az 6 karakter olmalıdır.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
        ]);

        // Aynı telefonla yarım kalmış (OTP doğrulanmamış) kayıt varsa temizle
        User::where('phone', $request->phone)
            ->where('status', 'pending')
            ->whereNull('phone_verified_at')
            ->delete();

        $user = User::create([
            'name'     => $request->name,
            'phone'    => $request->phone,
            'email'    => $request->email,
            'password' => $request->password ? Hash::make($request->password) : null,
            'status'   => 'pending',
        ]);

        $this->sms->sendOtp($user->phone, 'register');

        // Kayıt akışı boyunca kullanılacak kısa ömürlü token
        $token = $user->createToken('register-flow')->plainTextToken;

        return response()->json([
            'message' => 'Doğrulama kodu gönderildi.',
            'token'   => $token,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────
    // ADIM 2 — OTP doğrulama
    // POST /api/register/verify-otp
    // Auth: register-flow token
    // ─────────────────────────────────────────────────────────
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'otp' => 'required|digits:6',
        ], [
            'otp.digits' => '6 haneli doğrulama kodunu girin.',
        ]);

        $user = $request->user();

        if ($user->phone_verified_at) {
            return response()->json(['message' => 'Telefon zaten doğrulanmış.']);
        }

        try {
            $this->sms->verifyOtp($user->phone, $request->otp, 'register');
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['otp' => $e->getMessage()]);
        }

        $user->update(['phone_verified_at' => now()]);

        return response()->json(['message' => 'Telefon numarası doğrulandı.']);
    }

    // ─────────────────────────────────────────────────────────
    // ADIM 2B — OTP yeniden gönder
    // POST /api/register/resend-otp
    // Auth: register-flow token
    // ─────────────────────────────────────────────────────────
    public function resendOtp(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->sms->canResend($user->phone, 'register')) {
            $seconds = $this->sms->secondsUntilResend($user->phone, 'register');
            return response()->json([
                'message' => "Lütfen {$seconds} saniye bekleyin.",
                'seconds' => $seconds,
            ], 429);
        }

        $this->sms->sendOtp($user->phone, 'register');

        return response()->json(['message' => 'Yeni doğrulama kodu gönderildi.']);
    }

    // ─────────────────────────────────────────────────────────
    // ADIM 3 — Hesap türü + firma adı
    // POST /api/register/set-type
    // Auth: register-flow token
    // ─────────────────────────────────────────────────────────
    public function setAccountType(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->phone_verified_at) {
            return response()->json([
                'message' => 'Önce telefon numaranızı doğrulayın.',
                'code'    => 'PHONE_NOT_VERIFIED',
            ], 403);
        }

        $request->validate([
            'account_type' => 'required|in:buyer,emlakci,galerici,her_ikisi',
            'company_name' => 'nullable|string|max:255',
        ]);

        // ── Müşteri ──────────────────────────────────────────
        if ($request->account_type === 'buyer') {
            $user->update(['status' => 'active']);
            $user->assignRole('buyer');

            if ($user->email) {
                $this->email->sendVerification($user);
            }

            // Kayıt akışı tamamlandı — kalıcı token üret
            $user->tokens()->where('name', 'register-flow')->delete();
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message'            => 'Kayıt tamamlandı. Hoş geldiniz!',
                'requires_documents' => false,
                'status'             => 'active',
                'token'              => $token,
                'user'               => $this->userResponse($user),
            ]);
        }

        // ── Uzman ────────────────────────────────────────────
        if (!$request->company_name) {
            throw ValidationException::withMessages([
                'company_name' => 'Uzman hesabı için firma / işletme adı zorunludur.',
            ]);
        }

        $user->update([
            'agent_type'   => $request->account_type,
            'company_name' => $request->company_name,
            'status'       => 'pending',
        ]);
        $user->assignRole('agent');

        return response()->json([
            'message'            => 'Hesap türü belirlendi. Belgelerinizi yükleyin.',
            'requires_documents' => true,
            'status'             => 'pending',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ADIM 4 — Belge yükleme
    // POST /api/register/upload-documents
    // Auth: register-flow token
    // ─────────────────────────────────────────────────────────
    public function uploadDocuments(Request $request): JsonResponse
    {
        $request->validate([
            'isyeri_belgesi'  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'ticaret_sicili'  => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'esnaf_oda_kaydi' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'vergi_levhasi'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'ticaret_sicili.required' => 'Ticaret Sicil Kaydı zorunludur.',
            'vergi_levhasi.required'  => 'Vergi Levhası zorunludur.',
            '*.mimes'                 => 'Yalnızca PDF, JPG veya PNG yükleyebilirsiniz.',
            '*.max'                   => 'Her dosya en fazla 5MB olabilir.',
        ]);

        $user = $request->user();

        foreach (array_keys(AgentDocument::TYPE_LABELS) as $type) {
            if ($request->hasFile($type) && $request->file($type)->isValid()) {
                $file = $request->file($type);
                $path = $file->store("agent-documents/{$user->id}", 'private');

                AgentDocument::updateOrCreate(
                    ['user_id' => $user->id, 'document_type' => $type],
                    [
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type'     => $file->getMimeType(),
                        'file_size'     => $file->getSize(),
                    ]
                );
            }
        }

        if ($user->email) {
            $this->email->sendVerification($user);
        }

        return response()->json([
            'message' => 'Belgeler alındı. Başvurunuz incelemeye alındı.',
            'status'  => 'pending',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Private — API yanıtında kullanıcı verisi
    // ─────────────────────────────────────────────────────────
    private function userResponse(User $user): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'phone'        => $user->phone,
            'email'        => $user->email,
            'status'       => $user->status,
            'roles'        => $user->getRoleNames(),
            'company_name' => $user->company_name,
            'agent_type'   => $user->agent_type,
        ];
    }
}