<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Models\UserConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Yasal metinler — herkese açık (auth gerektirmez). Kayıt formu bunu
 * kullanıcı henüz hiç yokken çağırır (bkz. AuthPage.jsx registration
 * step). Ham {placeholder}'lı body ASLA dışarı çıkmaz, her zaman
 * renderedBody() (merge tag'leri çözülmüş) döner.
 */
class LegalDocumentController extends Controller
{
    // GET /api/legal-documents — 4 metnin tamamı, tek istekte. Bu route
    // auth middleware'i DIŞINDA (bkz. routes/api.php) — bu yüzden burada
    // $request->user() güvenilir şekilde çözülemez, kişiselleştirme
    // (kullanici_adi vb.) yapılmaz; merge tag'ler boş kalır.
    public function index(): JsonResponse
    {
        $documents = LegalDocument::query()
            ->orderBy('id')
            ->get()
            ->map(fn (LegalDocument $doc) => [
                'type'         => $doc->type,
                'title'        => $doc->title,
                'version'      => $doc->version,
                'is_mandatory' => $doc->is_mandatory,
                'body'         => $doc->renderedBody(),
            ]);

        return response()->json(['data' => $documents]);
    }

    // POST /api/user/legal-consents — LegalReconsentGate.jsx (bkz. frontend)
    // buradan, metin güncellendiği için tekrar onay istenen zorunlu
    // belgeleri kabul eder. 'types' verilmezse, kullanıcının o an
    // bekleyen TÜM zorunlu onayları kabul edilmiş sayılır.
    public function acceptConsents(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'types'   => 'sometimes|array',
            'types.*' => 'string',
        ]);

        $types = $validated['types']
            ?? $user->pendingConsents()->pluck('type')->all();

        foreach ($types as $type) {
            $doc = LegalDocument::where('type', $type)->first();
            if (!$doc) {
                continue;
            }

            // Zaten bu versiyonu onaylamışsa tekrar satır eklemeye gerek yok.
            if ($user->latestConsentVersion($type) >= $doc->version) {
                continue;
            }

            UserConsent::create([
                'user_id'             => $user->id,
                'legal_document_type' => $type,
                'version'             => $doc->version,
                'accepted_at'         => now(),
                'ip_address'          => $request->ip(),
            ]);
        }

        return response()->json([
            'message'          => 'Onayınız kaydedildi.',
            'pending_consents' => $user->pendingConsents(),
        ]);
    }

    // GET /api/user/legal-consents — Ayarlar > Yasal Metinler sekmesi
    // (bkz. SettingsPage.jsx) hangi metni ne zaman/hangi versiyonda
    // onayladığını göstermek için bunu çağırır.
    public function myConsents(Request $request): JsonResponse
    {
        $consents = $request->user()->consents()
            ->orderByDesc('accepted_at')
            ->get()
            ->unique('legal_document_type')
            ->map(fn (UserConsent $c) => [
                'type'        => $c->legal_document_type,
                'version'     => $c->version,
                'accepted_at' => $c->accepted_at,
            ])
            ->values();

        return response()->json(['data' => $consents]);
    }
}
