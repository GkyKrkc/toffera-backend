<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountTypeGroup;
use App\Models\AgentDocument;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\CategoryAccessService;
use App\Services\EmailService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __construct(
        private OtpService            $sms,
        private EmailService          $email,
        private CategoryAccessService $categoryAccess,
    ) {}

    // ─────────────────────────────────────────────────────────
    // ADIM 1 — Temel bilgiler + OTP gönder
    // POST /api/register
    // Auth: Yok
    //
    // OTP burada gönderiliyor (akışın en başında) — telefon numarası,
    // hesap türü seçimi ve belge yüklemesinden ÖNCE doğrulanıyor. Böylece
    // doğrulanmamış bir numarayla gerçek belge/ticaret bilgisi
    // gönderilmesi mümkün olmuyor.
    // ─────────────────────────────────────────────────────────
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|regex:/^[0-9]{10,11}$/|unique:users,phone',
            'password' => 'nullable|string|min:6|confirmed',
            'email'    => 'nullable|email|max:255|unique:users,email',
            // Kullanıcı Sözleşmesi + KVKK Aydınlatma Metni — tek, zorunlu
            // onay kutusu (bkz. AuthPage.jsx). Açık Rıza ve Ticari
            // Elektronik İleti KASITLI olarak burada YOK — KVKK gereği
            // isteğe bağlı, üyeliğin ön şartı yapılamaz.
            'kvkk_onay' => 'required|accepted',
            'acik_riza_onay'    => 'nullable|boolean',
            'ticari_ileti_onay' => 'nullable|boolean',
        ], [
            'phone.unique'       => 'Bu telefon numarası zaten kayıtlı.',
            'phone.regex'        => 'Geçerli bir telefon numarası girin (10-11 hane).',
            'email.unique'       => 'Bu e-posta adresi zaten kullanılıyor.',
            'password.min'       => 'Şifre en az 6 karakter olmalıdır.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
            'kvkk_onay.required' => 'Kullanıcı Sözleşmesi ve KVKK Aydınlatma Metni\'ni onaylamanız gerekiyor.',
            'kvkk_onay.accepted' => 'Kullanıcı Sözleşmesi ve KVKK Aydınlatma Metni\'ni onaylamanız gerekiyor.',
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

        // Yasal onaylar — Kullanıcı Sözleşmesi + KVKK Aydınlatma zorunlu,
        // Açık Rıza ve Ticari Elektronik İleti kullanıcı işaretlediyse.
        $this->recordConsent($user, LegalDocument::TYPE_USER_AGREEMENT, $request);
        $this->recordConsent($user, LegalDocument::TYPE_KVKK_DISCLOSURE, $request);
        if ($request->boolean('acik_riza_onay')) {
            $this->recordConsent($user, LegalDocument::TYPE_EXPLICIT_CONSENT, $request);
        }
        if ($request->boolean('ticari_ileti_onay')) {
            $this->recordConsent($user, LegalDocument::TYPE_COMMERCIAL_MSG, $request);
        }

        $this->sms->sendOtp($user->phone, 'register');

        // Kayıt akışı boyunca kullanılacak kısa ömürlü token
        $token = $user->createToken('register-flow')->plainTextToken;

        return response()->json([
            'message'   => 'Doğrulama kodu gönderildi.',
            'token'     => $token,
            'debug_otp' => $this->sms->debugCode($user->phone, 'register'), // sadece SMS_PROVIDER=log iken dolu
        ], 201);
    }

    // ─────────────────────────────────────────────────────────
    // ADIM 2 — OTP doğrulama (sadece telefonu doğrular, hesabı henüz
    // finalize etmez — finalize setAccountType/uploadDocuments'ta olur)
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
    // OTP yeniden gönder (Doğrulama adımında, artık akışın sonunda)
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

        return response()->json([
            'message'   => 'Yeni doğrulama kodu gönderildi.',
            'debug_otp' => $this->sms->debugCode($user->phone, 'register'),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Kayıt formu — seçilebilir hesap grupları listesi
    // GET /api/register/account-type-groups
    // Auth: Yok (public)
    // ─────────────────────────────────────────────────────────
    public function accountTypeGroups(): JsonResponse
    {
        $groups = AccountTypeGroup::active()
            ->with('categories:id,name,slug,required_documents')
            ->orderBy('sort_order')
            ->get()
            ->map(function (AccountTypeGroup $group) {
                return [
                    'id'                 => $group->id,
                    'name'               => $group->name,
                    'slug'               => $group->slug,
                    'kind'               => $group->kind,
                    'categories'         => $group->categories->map(fn ($c) => [
                        'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug,
                    ])->values(),
                    'required_documents' => $this->requiredDocumentsForGroup($group)->values(),
                ];
            });

        return response()->json(['data' => $groups]);
    }

    // ─────────────────────────────────────────────────────────
    // ADIM 3 — Hesap türü + firma adı (telefon doğrulanmış olmalı)
    // POST /api/register/set-type
    // Auth: register-flow token
    //
    // Bireysel VEYA belge istemeyen ticari gruplar için: başka adım
    // kalmadığından hesap burada finalize edilir (kalıcı token üretilir).
    // Belge isteyen ticari gruplar için finalize, uploadDocuments()'a
    // ertelenir.
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
            'account_type_group_id' => 'required|integer|exists:account_type_groups,id',
            'company_name'          => 'nullable|string|max:255',
        ]);

        $group = AccountTypeGroup::active()->find($request->account_type_group_id);

        if (!$group) {
            throw ValidationException::withMessages([
                'account_type_group_id' => 'Geçersiz veya pasif bir hesap grubu seçtiniz.',
            ]);
        }

        // ── Bireysel — burada finalize edilir ─────────────────
        if ($group->kind === 'individual') {
            $user->update([
                'account_type_group_id' => $group->id,
                'status'                 => 'active',
            ]);
            $user->assignRole('buyer');
            $this->categoryAccess->syncFromGroup($user->fresh());

            if ($user->email) {
                $this->email->sendVerification($user);
            }

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

        // ── Ticari ───────────────────────────────────────────
        if (!$request->company_name) {
            throw ValidationException::withMessages([
                'company_name' => 'Ticari hesap için firma / işletme adı zorunludur.',
            ]);
        }

        $requiredDocs = $this->requiredDocumentsForGroup($group);

        $user->update([
            'account_type_group_id' => $group->id,
            // Eski agent_type ENUM'u (emlakci/galerici/her_ikisi) sadece
            // bilinen 3 klasik grup için geriye dönük dolduruluyor — yeni
            // gruplar (Plaza, Rent A Car vb.) bu enum'a sığmadığı için null
            // kalır. PortfolioSidebar.jsx gibi hâlâ agent_type'a bakan eski
            // frontend kodları, account_type_group_id tabanlı sisteme
            // geçirilene kadar bu köprüye ihtiyaç duyar.
            'agent_type'   => $this->legacyAgentType($group->slug),
            'company_name' => $request->company_name,
        ]);
        $user->assignRole('agent');
        $this->categoryAccess->syncFromGroup($user->fresh());

        // Bu ticari grubun bağlı olduğu kategorilerde hiç belge tanımlı
        // değilse, belge adımını atlayıp burada finalize ediyoruz —
        // ticari hesap her zaman 'pending' kalır, admin onayı bekler.
        if ($requiredDocs->isEmpty()) {
            $user->update(['status' => 'pending']);

            if ($user->email) {
                $this->email->sendVerification($user);
            }

            $user->tokens()->where('name', 'register-flow')->delete();
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message'            => 'Kayıt tamamlandı. Başvurunuz incelemeye alındı.',
                'requires_documents' => false,
                'status'             => 'pending',
                'token'              => $token,
                'user'               => $this->userResponse($user),
            ]);
        }

        return response()->json([
            'message'            => 'Hesap türü belirlendi. Belgelerinizi yükleyin.',
            'requires_documents' => true,
            'required_documents' => $requiredDocs->values(), // [{key,label,required}, ...] — frontend formu dinamik kursun diye
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ADIM 4 — Belge yükleme (sadece belge isteyen ticari gruplar)
    // POST /api/register/upload-documents
    // Auth: register-flow token
    //
    // Bu noktada telefon zaten doğrulanmış (setAccountType bunu şart
    // koşuyordu) — belgeler kabul edildikten sonra hesap burada finalize
    // edilir: durum 'pending' (admin onayı bekler), kalıcı token üretilir.
    // ─────────────────────────────────────────────────────────
    public function uploadDocuments(Request $request): JsonResponse
    {
        $user  = $request->user();
        $group = $user->accountTypeGroup;

        if (!$group) {
            return response()->json([
                'message' => 'Önce hesap türünüzü seçin.',
                'code'    => 'ACCOUNT_TYPE_NOT_SET',
            ], 422);
        }

        $requiredDocs = $this->requiredDocumentsForGroup($group);

        $rules    = [];
        $messages = [
            '*.mimes' => 'Yalnızca PDF, JPG veya PNG yükleyebilirsiniz.',
            '*.max'   => 'Her dosya en fazla 5MB olabilir.',
        ];

        foreach ($requiredDocs as $doc) {
            $isRequired = $doc['required'] ?? true;
            $rules[$doc['key']] = ($isRequired ? 'required' : 'nullable') . '|file|mimes:pdf,jpg,jpeg,png|max:5120';
            $messages["{$doc['key']}.required"] = ($doc['label'] ?? $doc['key']) . ' zorunludur.';
        }

        $request->validate($rules, $messages);

        foreach ($requiredDocs as $doc) {
            $key = $doc['key'];
            if ($request->hasFile($key) && $request->file($key)->isValid()) {
                $file = $request->file($key);
                $path = $file->store("agent-documents/{$user->id}", 'private');

                AgentDocument::updateOrCreate(
                    ['user_id' => $user->id, 'document_type' => $key],
                    [
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type'     => $file->getMimeType(),
                        'file_size'     => $file->getSize(),
                    ]
                );
            }
        }

        $user->update(['status' => 'pending']);

        if ($user->email) {
            $this->email->sendVerification($user);
        }

        // Kayıt akışı tamamlandı — kalıcı token üret
        $user->tokens()->where('name', 'register-flow')->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Belgeler alındı. Başvurunuz incelemeye alındı.',
            'status'  => 'pending',
            'token'   => $token,
            'user'    => $this->userResponse($user),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Private — bir grubun bağlı olduğu tüm kategorilerin
    // required_documents listesini toplayıp key'e göre tekilleştirir.
    // Aynı belge (ör. "vergi_levhasi") birden fazla kategoride
    // tanımlıysa kullanıcıdan sadece bir kez istenir.
    // ─────────────────────────────────────────────────────────
    private function requiredDocumentsForGroup(AccountTypeGroup $group): \Illuminate\Support\Collection
    {
        return $group->categories()
            ->get()
            ->flatMap(fn ($category) => $category->required_documents ?? [])
            ->filter(fn ($doc) => !empty($doc['key']))
            ->unique('key');
    }

    // ─────────────────────────────────────────────────────────
    // Private — eski agent_type ENUM'una (emlakci/galerici/her_ikisi)
    // geriye dönük köprü. Sadece bilinen 3 klasik grup slug'ı için
    // dolduruluyor; yeni/özel gruplar (plaza, rent-a-car vb.) için
    // null döner. agent_type'a hâlâ bakan frontend kodları
    // (ör. PortfolioSidebar.jsx) account_type_group_id tabanlı
    // sisteme geçirilene kadar bu köprü korunmalı.
    // ─────────────────────────────────────────────────────────
    private function legacyAgentType(string $groupSlug): ?string
    {
        return match ($groupSlug) {
            'galericiler', 'galerici' => 'galerici',
            'emlakciler', 'emlakci'   => 'emlakci',
            'her-ikisi', 'her_ikisi'  => 'her_ikisi',
            default                    => null,
        };
    }

    // ─────────────────────────────────────────────────────────
    // Private — bir yasal metin tipi için kullanıcının onayını kaydeder.
    // İlgili LegalDocument satırı henüz seed edilmemişse (ör. deploy
    // sırasında migration'lar çalıştı ama seeder henüz çalışmadıysa)
    // sessizce atlanır — kayıt akışını asla kilitlemez.
    // ─────────────────────────────────────────────────────────
    private function recordConsent(User $user, string $type, Request $request): void
    {
        $doc = LegalDocument::where('type', $type)->first();
        if (!$doc) {
            return;
        }

        UserConsent::create([
            'user_id'              => $user->id,
            'legal_document_type'  => $type,
            'version'              => $doc->version,
            'accepted_at'          => now(),
            'ip_address'           => $request->ip(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Private — API yanıtında kullanıcı verisi
    // ─────────────────────────────────────────────────────────
    private function userResponse(User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'phone'             => $user->phone,
            'email'             => $user->email,
            'status'            => $user->status,
            'roles'             => $user->getRoleNames(),
            'company_name'      => $user->company_name,
            'agent_type'        => $user->agent_type,
            'account_type_group' => $user->accountTypeGroup ? [
                'id'   => $user->accountTypeGroup->id,
                'name' => $user->accountTypeGroup->name,
                'slug' => $user->accountTypeGroup->slug,
                'kind' => $user->accountTypeGroup->kind,
            ] : null,
            ...$user->entitlementSummary(), // credit_balance + active_subscription
        ];
    }
}
