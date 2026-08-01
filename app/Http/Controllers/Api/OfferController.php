<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demand;
use App\Models\Offer;
use App\Notifications\NewOfferReceived;
use App\Notifications\OfferAccepted as OfferAcceptedNotification;
use App\Notifications\OfferRejected as OfferRejectedNotification;
use App\Notifications\OfferWithdrawn;
use App\Notifications\SaleConfirmed;
use App\Notifications\OfferReinstated;
use App\Services\PortfolioMatcher;
use App\Services\CategoryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function __construct(
        private CategoryAccessService $categoryAccess,
    ) {}

    // ─────────────────────────────────────────────────────────
    // Teklife ait tüm detay — OfferDetailPage için
    // GET /api/buyer/offers/{offer}
    // ─────────────────────────────────────────────────────────
    public function show(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();

        $offer->loadMissing('demand');

        // Talebin sahibi VEYA teklifi veren uzman görebilir
        $isOwner = $offer->demand->isOwnedBy($user);
        $isAgent = $offer->user_id === $user->id;

        if (!$isOwner && !$isAgent) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $offer->load([
            'user:id,name,company_name,agent_type,phone',
            'demand:id,title,status,user_id,category_id',
            'demand.user:id,name,phone',
            'demand.category:id,name,slug',
            'portfolioItem.images',
            'revisions',
        ]);

        if (!$offer->portfolioItem && $offer->portfolio_item_id) {
            $item = \App\Models\PortfolioItem::with([
                'images' => fn($q) => $q->orderBy('sort_order')->orderBy('id'),
            ])->find($offer->portfolio_item_id);
            $offer->setRelation('portfolioItem', $item);
        }

        // Değerlendirme butonları sadece ilan sahibine
        $offer->can_evaluate  = $isOwner && $offer->status === 'pending';

        // İletişim bilgisi: kabul sonrası her iki tarafa da (karşılıklı)
        // otomatik açılır. Kabul ÖNCESİNDE ise sadece talep sahibinin kendi
        // isteğiyle erken paylaştığı durumda (contact_revealed_at) — ve bu
        // sadece TEKLİFİ VEREN ACENTE yönünde anlamlı, çünkü talep sahibi
        // zaten kendi bilgisini biliyor.
        $offer->can_see_contact = $offer->status === 'accepted'
            || ($isAgent && $offer->contact_revealed_at !== null);

        // Talep sahibi kendi teklifini erken paylaşmış mı — sadece o kendi
        // görünümünde bu durumu görsün diye ayrı bir bayrak.
        $offer->contact_already_revealed = $offer->contact_revealed_at !== null;

        // Kabul edilmiş ama satışı henüz onaylanmamış bir teklif için:
        // acente vazgeçebilir, talep sahibi satışı onaylayabilir.
        $offer->can_withdraw     = $isAgent && $offer->isWithdrawable();
        $offer->can_confirm_sale = $isOwner && $offer->isWithdrawable();

        // Mesajlaşma — "Görüşme Başlat" sadece talep sahibine gösterilir,
        // uzman tarafı sadece var olan bir konuşması varsa mesaj yazabilir.
        $offer->can_start_conversation = $isOwner;
        $offer->conversation_id = $offer->conversation?->id;

        return response()->json(['data' => $offer]);
    }

    // ─────────────────────────────────────────────────────────
    // Teklifi favorilere ekle/çıkar (talep sahibi)
    // POST /api/buyer/offers/{offer}/favorite
    // ─────────────────────────────────────────────────────────
    public function toggleFavorite(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();

        $offer->loadMissing('demand');
        if (!$offer->demand || !$offer->demand->isOwnedBy($user)) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $offer->update(['is_favorited' => !$offer->is_favorited]);

        return response()->json([
            'message'      => $offer->is_favorited ? 'Teklif favorilere eklendi.' : 'Teklif favorilerden çıkarıldı.',
            'is_favorited' => $offer->is_favorited,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Talep sahibi, KABUL ETMEDEN de kendi iletişim bilgisini bu teklifi
    // veren acenteyle erken paylaşabilir — nihai bir karar değil, sadece
    // görüşmeyi hızlandırmak isteyenler için.
    // POST /api/buyer/offers/{offer}/reveal-contact
    // ─────────────────────────────────────────────────────────
    public function revealContact(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();

        $offer->loadMissing('demand');
        if (!$offer->demand || !$offer->demand->isOwnedBy($user)) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        if (!in_array($offer->status, ['pending', 'reviewing', 'accepted'], true)) {
            return response()->json(['message' => 'Bu teklif için iletişim bilgisi paylaşılamaz.'], 422);
        }

        if (!$offer->contact_revealed_at) {
            $offer->update(['contact_revealed_at' => now()]);
        }

        return response()->json(['message' => 'İletişim bilginiz acenteyle paylaşıldı.']);
    }

    // ─────────────────────────────────────────────────────────
    // Talebe teklif ver
    // POST /api/agent/demands/{demand}/offers
    // ─────────────────────────────────────────────────────────
    public function store(Request $request, Demand $demand): JsonResponse
    {
        $user = $request->user();

        $demand->loadMissing('category');

        if (!$demand->isActive()) {
            return response()->json(['message' => 'Bu talep artık aktif değil.'], 422);
        }

        if ($demand->user_id === $user->id) {
            return response()->json(['message' => 'Kendi talebinize teklif veremezsiniz.'], 422);
        }

        // ── Teklif Verme Yetkisi (abonelik/kontör — role bağımsız) ──
        // Eskiden: checkCategoryAllowed() (sadece agent_type) + canMakeOffer()
        // (sadece offer_limit) ayrı ayrı kontrol ediliyordu, ikisi de SADECE
        // agent rolündeki kullanıcılara izin veriyordu. Artık normal bir
        // kullanıcı da kontör/abonelik satın alarak teklif verebilir.
        $categorySlug = $demand->category?->slug;

        if (Offer::where('demand_id', $demand->id)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Bu talebe zaten teklif verdiniz.'], 422);
        }

        // portfolio_item_id artık ZORUNLU — "alakasız kategori/marka ile
        // teklif verme" boşluğunu kapatmak için portföysüz serbest teklif
        // kaldırıldı, her teklif somut bir portföy öğesine bağlanmalı.
        $request->validate([
            'price'             => 'required|numeric|min:1',
            'message'           => 'nullable|string|max:500',
            'portfolio_item_id' => 'required|integer|exists:portfolio_items,id',
        ], [
            'price.required'             => 'Teklif fiyatı zorunludur.',
            'price.min'                  => 'Geçerli bir fiyat girin.',
            'portfolio_item_id.required' => 'Teklif vermek için bir portföy öğesi seçmelisiniz.',
        ]);

        $portfolioItem = \App\Models\PortfolioItem::find($request->portfolio_item_id);

        // ── Sahiplik kontrolü ──
        // Başkasının portföy öğesiyle teklif verilemez. Önceden sadece
        // update() bunu kontrol ediyordu, store()'da hiç yoktu — açık kapıydı.
        if ($portfolioItem->user_id !== $user->id) {
            return response()->json(['message' => 'Bu portföy öğesi size ait değil.'], 403);
        }

        // ── Kategori tipi kontrolü ──
        // Emlak talebine vasıta portföyüyle (veya tam tersi) teklif verilmesini
        // engeller. categorySlug demand->category->slug, portfolioItem->type
        // ile aynı taksonomiyi kullanıyor (vasita | gayrimenkul | elektronik).
        if (!$categorySlug || $portfolioItem->type !== $categorySlug) {
            return response()->json(['message' => 'Seçtiğiniz portföy öğesi bu talebin kategorisiyle uyuşmuyor.'], 422);
        }

        // ── YETKİ (capability) — bu kategoride iş yapmaya yetkili mi? ──
        // Kaynak: user_category_permissions (CategoryAccessService).
        // Aşağıdaki canOfferInCategory() kontrolünden BAĞIMSIZ ve ONDAN
        // ÖNCE gelir — o kontör/abonelik ("kapasite") sorar, bu ise
        // "bu iş kolunda hiç teklif verme hakkın var mı" sorar. İkisi de
        // ayrı ayrı sağlanmalı.
        if (!$categorySlug || !$this->categoryAccess->hasOfferCapability($user, $demand->category)) {
            return response()->json([
                'message' => 'Bu kategoride teklif verme yetkiniz yok.',
                'code'    => 'CATEGORY_OFFER_NOT_ALLOWED',
            ], 403);
        }

        if (!$categorySlug || !$user->canOfferInCategory($categorySlug, $portfolioItem)) {
            $message = $portfolioItem && !$user->isAgent() && !$portfolioItem->isOwnershipVerified()
                ? 'Bu öğe için teklif verebilmeniz için önce sahiplik belgesi (ruhsat/tapu) yüklemeniz gerekiyor.'
                : 'Bu kategoride teklif verme hakkınız yok. Abonelik satın alın veya kontör yükleyin.';

            return response()->json([
                'message'          => $message,
                'code'             => 'OFFER_NOT_ALLOWED',
                'upgrade_required' => true,
            ], 403);
        }

        // ── Eşleşme yüzdesi kontrolü ──
        // TODO: Gerçek form-bazlı kesin eşleşme mantığı (marka/model/yıl birebir,
        // km aralık kontrolü vb.) PortfolioMatcher'a eklenince, aşağıdaki sabit
        // 100 yerine PortfolioMatcher::calculateExactMatchPercent($portfolioItem, $demand)
        // gibi bir çağrıyla değiştirilecek. Şimdilik her uygun öğe %100 kabul ediliyor,
        // yani bu kontrol şu an fiilen devre dışı (hiçbir teklifi reddetmiyor) ama
        // demand->min_match_percent alanı ve iskelet hazır.
        $matchPercent = 100;

        if ($demand->min_match_percent !== null && $matchPercent < $demand->min_match_percent) {
            return response()->json([
                'message' => "Bu portföy öğesi talebin istediği minimum eşleşme oranını (%{$demand->min_match_percent}) karşılamıyor.",
            ], 422);
        }

        $offer = Offer::create([
            'demand_id'         => $demand->id,
            'user_id'           => $user->id,
            'price'             => $request->price,
            'message'           => $request->message,
            'portfolio_item_id' => $request->portfolio_item_id,
            'status'            => 'pending',
        ]);

        // Teklif başarıyla oluşturulduktan SONRA hakkı düş (abonelik kotası
        // veya kontör/süreli hak) — önce düşüp sonra Offer::create() başarısız
        // olursa kullanıcının hakkı boşa gitmesin diye sıra bu şekilde.
        $user->consumeOfferEntitlement($categorySlug, $offer, $portfolioItem);

        $offer->load([
            'demand:id,title,user_id',
            'user:id,name,company_name',
            'portfolioItem',
            'portfolioItem.images',
        ]);

        // ÖNEMLİ: broadcast + "yeni teklif aldınız" bildirimi ARTIK BURADA
        // gönderilmiyor — moderation_status='pending' durumundayken talep
        // sahibi bu teklifi göremiyor (demandOffers() filtreliyor), o yüzden
        // erken bildirim göndermek kafa karıştırır. Bu ikisi, admin teklifi
        // onayladığı anda (Filament aksiyonunda) tetiklenmeli.

        return response()->json([
            'message' => 'Teklifiniz gönderildi, incelemeye alındı. Onaylandığında talep sahibine iletilecek.',
            'offer'   => $offer,
        ], 201);
    }
    // ─────────────────────────────────────────────────────────
    // Teklifi güncelle — SADECE pending durumdayken
    // PUT /api/agent/offers/{offer}
    //
    // Kural: Teklif "pending" olduğu sürece (müşteri henüz kabul/red
    // etmemişken) teklif sahibi fiyat/mesaj/portföyünü güncelleyebilir.
    // ─────────────────────────────────────────────────────────
    public function update(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();

        // Sadece teklifin sahibi
        if ($offer->user_id !== $user->id) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        // Beklemedeki VEYA geri çekilmiş (withdrawn) bir teklif güncellenebilir.
        // Geri çekilmiş bir teklifi güncellemek, onu fiilen yeniden
        // canlandırıp 'pending' durumuna döndürür — acente sıfırdan yeni
        // teklif oluşturmak zorunda kalmaz.
        if (!$offer->isPending() && !$offer->isWithdrawn()) {
            return response()->json([
                'message' => 'Bu teklif müşteri tarafından değerlendirildiği için artık güncellenemez.',
                'code'    => 'OFFER_NOT_PENDING',
            ], 422);
        }

        $isReactivation = $offer->isWithdrawn();

        // Spam/hız sınırı: bir teklif en az UPDATE_COOLDOWN_MINUTES dakikada
        // bir güncellenebilir (created_at da updated_at'e sayıldığı için,
        // teklif verildikten hemen sonra art arda güncellemeyi de kapsar).
        if (!$offer->canBeUpdatedNow()) {
            return response()->json([
                'message' => "Bir teklifi en az " . Offer::UPDATE_COOLDOWN_MINUTES . " dakikada bir güncelleyebilirsiniz. Lütfen {$offer->updateCooldownRemainingMinutes()} dakika sonra tekrar deneyin.",
                'code'    => 'OFFER_UPDATE_COOLDOWN',
            ], 429);
        }

        $offer->loadMissing('demand');

        // Talep hâlâ aktif mi?
        if (!$offer->demand || !$offer->demand->isActive()) {
            return response()->json(['message' => 'Bu talep artık aktif değil.'], 422);
        }

        $validated = $request->validate([
            'price'             => 'required|numeric|min:1',
            'message'           => 'nullable|string|max:500',
            'portfolio_item_id' => 'nullable|integer|exists:portfolio_items,id',
        ], [
            'price.required' => 'Teklif fiyatı zorunludur.',
            'price.min'      => 'Geçerli bir fiyat girin.',
        ]);

        // Seçilen portföy öğesi gerçekten bu acenteye mi ait?
        if (!empty($validated['portfolio_item_id'])) {
            $ownsItem = \App\Models\PortfolioItem::where('id', $validated['portfolio_item_id'])
                ->where('user_id', $user->id)
                ->exists();
            if (!$ownsItem) {
                return response()->json(['message' => 'Seçilen portföy öğesi size ait değil.'], 422);
            }
        }

        // Değişiklikten ÖNCEKİ hâli log'a yaz — teklifin geçmişi (kaç kere,
        // ne şekilde değiştiği) offer_revisions'da ayrı ayrı satırlar olarak
        // tutulur. İlk hâl (oluşturma anı) burada YOK — offers.created_at
        // zaten onu temsil ediyor, bu tablo sadece SONRAKİ revizyonları tutar.
        $offer->revisions()->create([
            'price'             => $offer->price,
            'message'           => $offer->message,
            'portfolio_item_id' => $offer->portfolio_item_id,
        ]);

        $wasApproved = $offer->moderation_status === 'approved';

        $offer->update([
            'price'             => $validated['price'],
            'message'           => $validated['message'] ?? null,
            'portfolio_item_id' => $validated['portfolio_item_id'] ?? null,
            // Geri çekilmiş bir teklif güncellendiğinde fiilen yeniden
            // gönderilmiş sayılır — 'pending'e döner.
            'status'            => $isReactivation ? 'pending' : $offer->status,
        ]);

        $offer->load([
            'demand:id,title,user_id',
            'user:id,name,company_name',
            'portfolioItem',
            'portfolioItem.images',
            'revisions',
        ]);

        // Müşteriye canlı güncelleme (aynı NewOffer kanalını kullanır — liste tazelenir)
        broadcast(new \App\Events\NewOffer($offer))->toOthers();

        // "Teklif güncellendi" bildirimi — SADECE teklif zaten admin
        // onaylıysa (talep sahibi zaten görebiliyorsa) gönderilir. Henüz
        // moderasyon bekleyen bir teklifin güncellenmesi talep sahibine
        // hiç anlam ifade etmez, o teklifi zaten göremiyor.
        if ($wasApproved && $offer->demand?->user_id) {
            $owner = \App\Models\User::find($offer->demand->user_id);
            $owner?->notify(new \App\Notifications\OfferUpdated($offer));
        }

        return response()->json([
            'message' => $isReactivation
                ? 'Teklifiniz güncellenerek tekrar gönderildi.'
                : 'Teklifiniz güncellendi.',
            'offer'   => $offer,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Bu talebe teklif verirken kullanılabilecek UYGUN portföyüm
    // GET /api/agent/demands/{demand}/matching-portfolio
    //
    // Modal bu listeyi çeker: sadece marka/model (emlakta tip) uyumlu
    // portföy öğeleri, en yüksek eşleşme yüzdesinden aşağıya sıralı.
    // ─────────────────────────────────────────────────────────
    public function matchingPortfolio(Request $request, Demand $demand): JsonResponse
    {
        $user = $request->user();
        $demand->loadMissing('category');

        // NOT: Burada kasıtlı olarak canOfferInCategory() kontrolü YOK.
        // Bu endpoint sadece "portföyümden hangisi bu talebe uygun" diye
        // GÖZATMA — henüz bir taahhüt yok, kontör harcanmıyor. Kontör/
        // abonelik kontrolü SADECE gerçek teklif gönderiminde (store())
        // yapılmalı. Eskiden burada da aynı kontrol vardı — bu da
        // kontörü 0 olan kullanıcının portföyünü hiç görememesine (ne
        // teklif edebileceğini bile keşfedememesine) yol açıyordu.

        if (!$demand->isActive()) {
            return response()->json(['message' => 'Bu talep artık aktif değil.'], 422);
        }

        $matches = PortfolioMatcher::matchingPortfolioForAgent($demand, $user->id);

        $data = $matches->map(function ($m) {
            $item = $m['item'];
            return [
                'id'          => $item->id,
                'title'       => $item->title,
                'price'       => $item->price,
                'type'        => $item->type,
                'district'    => $item->district,
                'features'    => $item->features,
                'cover'       => optional($item->images()->firstWhere('is_cover', true))->url
                    ?? optional($item->images()->first())->url,
                'match_score' => $m['score'],
                'match_percent' => $m['percent'],
            ];
        });

        return response()->json([
            'data'  => $data,
            'count' => $data->count(),
        ]);
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
            ->where('moderation_status', 'approved') // admin onaylamadan talep sahibi görmesin
            ->with([
                'user:id,name,company_name,agent_type,phone',
                'portfolioItem' => function ($q) {
                    $q->with(['images' => function ($qi) {
                        $qi->orderBy('sort_order')->orderBy('id');
                    }]);
                },
            ])
            ->latest()
            ->get();

        return response()->json($offers);
    }

    // ─────────────────────────────────────────────────────────
    // Teklifi kabul et — bu bir ÖN ANLAŞMADIR, kesin satış değildir.
    // Talep 'matched' durumuna geçer ('completed' değil). Satış gerçekten
    // tamamlanınca talep sahibi ayrıca confirmSale() ile onaylar; o ana
    // kadar acente withdraw() ile bu kabulden vazgeçebilir.
    // POST /api/buyer/offers/{offer}/accept
    // ─────────────────────────────────────────────────────────
    public function accept(Request $request, Offer $offer): JsonResponse
    {
        $user   = $request->user();
        $demand = $offer->demand;

        if (!$demand->isOwnedBy($user)) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        if (!in_array($offer->status, ['pending', 'reviewing'], true)) {
            return response()->json(['message' => 'Bu teklif zaten yanıtlandı.'], 422);
        }

        $offer->update(['status' => 'accepted']);

        // Kardeş teklifler 'rejected' olur — ama acente sonradan bu kabulden
        // vazgeçerse (withdraw) hangi tekliflerin hangi durumdan geldiğini
        // bilmemiz lazım, o yüzden ÖNCEKİ durumlarını + reddedilme nedenini
        // tek tek işaretliyoruz (foreach — pratikte bir talebe birkaç teklif
        // gelir, toplu update'e göre performans farkı önemsiz).
        $demand->offers()
            ->where('id', '!=', $offer->id)
            ->whereIn('status', ['pending', 'reviewing'])
            ->get()
            ->each(function (Offer $sibling) {
                $sibling->update([
                    'status'                  => 'rejected',
                    'status_before_rejection' => $sibling->status,
                    'rejected_reason'         => 'lost_to_accepted_offer',
                ]);
                // Kaybeden teklifin mesajlaşması varsa "Önceki Mesajlar"a düşsün.
                $sibling->conversation?->close();
            });

        $demand->update(['status' => 'matched']);

        broadcast(new \App\Events\DemandStatusChanged($demand->fresh()));

        $acceptedOffer = $offer->fresh([
            'demand.user',
            'user',
            'portfolioItem',
            'portfolioItem.images',
        ]);

        broadcast(new \App\Events\OfferAccepted($acceptedOffer));

        // Bildirim: teklifi veren acenteye "teklifiniz kabul edildi"
        $acceptedOffer->user->notify(new OfferAcceptedNotification($acceptedOffer));

        return response()->json([
            'message' => 'Teklif kabul edildi. Satış tamamlandığında lütfen onaylamayı unutmayın.',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Kabul edilmiş bir teklifi acente geri çeker — gerçek satış henüz
    // gerçekleşmediyse (araç elden çıkmadıysa) bu kesin bir final değildir.
    // Talep tekrar 'active' olur (expires_at DEĞİŞMEZ — bonus süre yok),
    // bu kabul yüzünden otomatik reddedilen kardeş teklifler eski
    // durumlarına geri döner.
    // POST /api/agent/offers/{offer}/withdraw
    // ─────────────────────────────────────────────────────────
    public function withdraw(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();

        if ($offer->user_id !== $user->id) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        if (!$offer->isWithdrawable()) {
            $message = $offer->isSaleConfirmed()
                ? 'Bu teklif için satış onaylandı, artık vazgeçilemez.'
                : 'Sadece kabul edilmiş teklifler geri çekilebilir.';

            return response()->json(['message' => $message], 422);
        }

        $offer->loadMissing('demand');
        $demand = $offer->demand;

        $offer->update(['status' => 'withdrawn']);

        if ($demand) {
            $demand->update(['status' => 'active']); // expires_at kasıtlı olarak dokunulmuyor

            // Bu kabul yüzünden reddedilmiş kardeş teklifleri eski durumuna geri getir
            $demand->offers()
                ->where('id', '!=', $offer->id)
                ->where('status', 'rejected')
                ->where('rejected_reason', 'lost_to_accepted_offer')
                ->get()
                ->each(function (Offer $sibling) {
                    $restoredStatus = $sibling->status_before_rejection ?: 'pending';

                    $sibling->update([
                        'status'                  => $restoredStatus,
                        'status_before_rejection' => null,
                        'rejected_reason'         => null,
                    ]);

                    $sibling->loadMissing('user', 'demand:id,title');
                    if ($sibling->user) {
                        $sibling->user->notify(new OfferReinstated($sibling));
                    }
                    // accept() sırasında kapatılmış konuşma varsa geri aç —
                    // teklif tekrar yarışa girdi.
                    $sibling->conversation?->reopen();
                });

            $demand->user?->notify(new OfferWithdrawn($offer));
        }

        return response()->json(['message' => 'Teklifiniz geri çekildi. Talep tekrar yayında.']);
    }

    // ─────────────────────────────────────────────────────────
    // Talep sahibi, kabul ettiği teklifle gerçek satışın tamamlandığını
    // onaylar — bu KESİN bir işlemdir, bundan sonra acente vazgeçemez.
    // POST /api/buyer/offers/{offer}/confirm-sale
    // ─────────────────────────────────────────────────────────
    public function confirmSale(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();

        $offer->loadMissing('demand');

        if (!$offer->demand || !$offer->demand->isOwnedBy($user)) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        if (!$offer->isWithdrawable()) {
            $message = $offer->isSaleConfirmed()
                ? 'Bu satış zaten onaylandı.'
                : 'Sadece kabul edilmiş teklifler için satış onaylanabilir.';

            return response()->json(['message' => $message], 422);
        }

        $offer->update(['sale_confirmed_at' => now()]);
        $offer->demand->update(['status' => 'completed']);

        $offer->loadMissing('user');
        $offer->user?->notify(new SaleConfirmed($offer));

        // Satış kesinleşti — mesajlaşma varsa "Önceki Mesajlar"a düşsün.
        $offer->conversation?->close();

        return response()->json(['message' => 'Satış onaylandı. Talep tamamlandı olarak işaretlendi.']);
    }

    // ─────────────────────────────────────────────────────────
    // Teklifi değerlendirmeye al — SADECE durum işaretleme, nihai
    // bir karar değil. pending → reviewing. accept/reject sonrası
    // için hâlâ açık kalır (değerlendirmedeyken de kabul/red edilebilir).
    // POST /api/buyer/offers/{offer}/review
    // ─────────────────────────────────────────────────────────
    public function review(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();

        if (!$offer->demand->isOwnedBy($user)) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        if (!$offer->isPending()) {
            return response()->json(['message' => 'Bu teklif zaten değerlendiriliyor veya yanıtlandı.'], 422);
        }

        $offer->update(['status' => 'reviewing']);

        return response()->json(['message' => 'Teklif değerlendirmeye alındı.']);
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

        if (!in_array($offer->status, ['pending', 'reviewing'], true)) {
            return response()->json(['message' => 'Bu teklif zaten yanıtlandı.'], 422);
        }

        $offer->update(['status' => 'rejected']);

        // Bildirim: teklifi veren acenteye "teklifiniz değerlendirildi"
        $offer->loadMissing('user', 'demand:id,title');
        $offer->user->notify(new OfferRejectedNotification($offer));

        // Teklif elendi — mesajlaşma varsa "Önceki Mesajlar"a düşsün.
        $offer->conversation?->close();

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
