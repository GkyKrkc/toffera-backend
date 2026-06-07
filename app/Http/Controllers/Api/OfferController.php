<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demand;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // Talebe teklif ver
    // POST /api/agent/demands/{demand}/offers
    // ─────────────────────────────────────────────────────────
    public function store(Request $request, Demand $demand): JsonResponse
    {
        $user = $request->user();

        // Category ilişkisini yükle
        $demand->loadMissing('category');

        // Talep aktif mi?
        if (!$demand->isActive()) {
            return response()->json(['message' => 'Bu talep artık aktif değil.'], 422);
        }

        // Kendi talebine teklif veremez
        if ($demand->user_id === $user->id) {
            return response()->json(['message' => 'Kendi talebinize teklif veremezsiniz.'], 422);
        }

        // ── Kategori - Uzman Tipi Uyum Kontrolü ──────────────
        $categorySlug = $demand->category?->slug;
        $agentType    = $user->agent_type;

        $allowed = match($agentType) {
            'emlakci'   => ['gayrimenkul'],
            'galerici'  => ['vasita'],
            'her_ikisi' => ['gayrimenkul', 'vasita'],
            default     => [],
        };

        if ($categorySlug && !in_array($categorySlug, $allowed)) {
            $tipLabel = match($agentType) {
                'emlakci'  => 'Emlakçı',
                'galerici' => 'Galerici',
                default    => 'Uzman',
            };
            return response()->json([
                'message' => "$tipLabel hesabınız bu kategori için teklif veremez.",
                'code'    => 'CATEGORY_NOT_ALLOWED',
            ], 403);
        }
        // ─────────────────────────────────────────────────────

        // Teklif limiti kontrolü
        if (!$user->canMakeOffer()) {
            return response()->json([
                'message'          => 'Bu ay için teklif limitinize ulaştınız.',
                'code'             => 'OFFER_LIMIT_REACHED',
                'upgrade_required' => true,
            ], 403);
        }

        // Daha önce teklif vermiş mi?
        if (Offer::where('demand_id', $demand->id)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Bu talebe zaten teklif verdiniz.'], 422);
        }

        $request->validate([
            'price'   => 'required|numeric|min:1',
            'message' => 'nullable|string|max:500',
        ], [
            'price.required' => 'Teklif fiyatı zorunludur.',
            'price.min'      => 'Geçerli bir fiyat girin.',
        ]);

        $offer = Offer::create([
            'demand_id' => $demand->id,
            'user_id'   => $user->id,
            'price'     => $request->price,
            'message'   => $request->message,
            'status'    => 'pending',
        ]);

        $offer->load(['demand:id,title,user_id', 'user:id,name,company_name']);
        // Gerçek zamanlı bildirim
        broadcast(new \App\Events\NewOffer($offer))->toOthers();
        return response()->json([
            'message' => 'Teklifiniz gönderildi.',
            'offer'   => $offer,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────
    // Agent'ın kendi teklifleri
    // GET /api/agent/offers
    // ─────────────────────────────────────────────────────────
    public function myOffers(Request $request): JsonResponse
    {
        $offers = Offer::where('user_id', $request->user()->id)
            ->with([
                'demand:id,title,status,category_id,user_id',
                'demand.category:id,name,slug',
            ])
            ->latest()
            ->paginate(20);

        // Tamamlanmış taleplerin kabul edilen teklif fiyatını ekle
        $offers->getCollection()->transform(function ($offer) {
            if ($offer->demand?->status === 'completed') {
                $accepted = Offer::where('demand_id', $offer->demand_id)
                    ->where('status', 'accepted')
                    ->first(['id', 'price', 'user_id']);
                if ($accepted) {
                    $isMine = $accepted->user_id === $offer->user_id;
                    $offer->accepted_offer = [
                        'price'   => $accepted->price,
                        'is_mine' => $isMine,
                    ];

                    // Sadece kabul edilen agent ilan sahibinin iletisim bilgisini gorebilir
                    if ($isMine) {
                        $owner = \App\Models\User::find($offer->demand->user_id, ['id', 'name', 'phone']);
                        $offer->demand_owner_contact = $owner ? [
                            'name'  => $owner->name,
                            'phone' => $owner->phone,
                        ] : null;
                    }
                }
            }
            return $offer;
        });

        return response()->json($offers);
    }

    // ─────────────────────────────────────────────────────────
    // Talebe gelen teklifler (müşteri görür)
    // GET /api/buyer/demands/{demand}/offers
    // ─────────────────────────────────────────────────────────
    public function demandOffers(Request $request, Demand $demand): JsonResponse
    {
        if (!$demand->isOwnedBy($request->user())) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $offers = $demand->offers()
            ->with(['user:id,name,company_name,agent_type'])
            ->latest()
            ->get();

        return response()->json($offers);
    }

    // ─────────────────────────────────────────────────────────
    // Teklifi kabul et
    // POST /api/buyer/offers/{offer}/accept
    // ─────────────────────────────────────────────────────────
    public function accept(Request $request, Offer $offer): JsonResponse
    {
        $user   = $request->user();
        $demand = $offer->demand;

        if (!$demand->isOwnedBy($user)) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        if (!$offer->isPending()) {
            return response()->json(['message' => 'Bu teklif zaten yanıtlandı.'], 422);
        }

        // Kabul edilen teklif
        $offer->update(['status' => 'accepted']);

        // Diğer teklifleri reddet
        $demand->offers()
            ->where('id', '!=', $offer->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        // Talebi tamamlandı yap
        $demand->update(['status' => 'completed']);

        // Anlık durum değişikliği bildirimi
        broadcast(new \App\Events\DemandStatusChanged($demand->fresh()));
        // Agent'a kabul bildirimi
        broadcast(new \App\Events\OfferAccepted($offer->fresh(['demand.user'])));
        return response()->json([
            'message' => 'Teklif kabul edildi. Talep tamamlandı olarak işaretlendi.',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Teklifi reddet
    // POST /api/buyer/offers/{offer}/reject
    // ─────────────────────────────────────────────────────────
    public function reject(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();

        if (!$offer->demand->isOwnedBy($user)) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        if (!$offer->isPending()) {
            return response()->json(['message' => 'Bu teklif zaten yanıtlandı.'], 422);
        }

        $offer->update(['status' => 'rejected']);

        return response()->json(['message' => 'Teklif reddedildi.']);
    }

    // ─────────────────────────────────────────────────────────
    // Teklifi iptal et (agent)
    // POST /api/agent/offers/{offer}/cancel
    // ─────────────────────────────────────────────────────────
    public function cancel(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();

        if ($offer->user_id !== $user->id) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        if (!$offer->isPending()) {
            return response()->json(['message' => 'Sadece beklemedeki teklifler iptal edilebilir.'], 422);
        }

        $offer->delete();

        return response()->json(['message' => 'Teklifiniz iptal edildi.']);
    }
}
