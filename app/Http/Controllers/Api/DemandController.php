<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Demand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\PortfolioMatcher;
use App\Services\CategoryAccessService;

class DemandController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // Pazaryeri listesi — herkese açık, filtreli
    // GET /api/demands
    // ─────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Demand::active()
            ->where('moderation_status', 'approved')
            ->with(['category:id,name,slug,icon'])
            ->withCount(['offers' => fn($q) => $q->where('moderation_status', 'approved')]);

        if ($request->category) {
            $query->byCategory($request->category);
        }

        if ($request->district) {
            $query->byDistrict($request->district);
        }

        // Anasayfa/vitrin üst filtre çubuğundaki İl/İlçe/Mahalle seçimleri.
        // Konum, talep oluştururken `features` JSON'ı içine yazılıyor
        // (il/ilce/mahalleler — bkz. store()), ayrı sütun değil. Eski
        // serbest metin `district` sütunu da (varsa) yedek olarak taranıyor.
        if ($request->filled('il')) {
            $il = $request->il;
            $query->where(function ($q) use ($il) {
                $q->where('features->il', $il)
                    ->orWhere('district', 'like', "%{$il}%");
            });
        }

        if ($request->filled('ilce')) {
            $ilce = $request->ilce;
            $query->where(function ($q) use ($ilce) {
                $q->where('features->ilce', $ilce)
                    ->orWhere('district', 'like', "%{$ilce}%");
            });
        }

        if ($request->filled('mahalle')) {
            $mahalleler = array_filter((array) $request->mahalle);
            if ($mahalleler) {
                $query->where(function ($q) use ($mahalleler) {
                    foreach ($mahalleler as $m) {
                        $q->orWhereJsonContains('features->mahalleler', $m)
                            ->orWhere('neighborhood', $m);
                    }
                });
            }
        }

        if ($request->min_budget || $request->max_budget) {
            $query->byBudget($request->min_budget, $request->max_budget);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%")
                    ->orWhere('district', 'like', "%{$request->search}%");
            });
        }

        match ($request->sort) {
            'oldest'      => $query->oldest(),
            'most_offers' => $query->orderByDesc('offers_count'),
            'budget_desc' => $query->orderByDesc('max_budget'),
            'budget_asc'  => $query->orderBy('min_budget'),
            default       => $query->latest(),
        };

        $perPage = min((int) $request->input('per_page', 12), 48);
        $demands = $query->paginate($perPage);

        return response()->json($demands);
    }

    // ─────────────────────────────────────────────────────────
    // Müşterinin kendi talepleri
    // GET /api/buyer/demands
    // Not: bilerek moderation_status filtresi YOK — kullanıcı kendi
    // pending/rejected talebini de görebilmeli (durumunu takip edebilsin).
    // ─────────────────────────────────────────────────────────
    public function myDemands(Request $request): JsonResponse
    {
        $demands = Demand::where('user_id', $request->user()->id)
            ->with(['category:id,name,slug'])
            ->withCount(['offers' => fn($q) => $q->where('moderation_status', 'approved')])
            ->latest()
            ->paginate(20);

        return response()->json($demands);
    }

    // ─────────────────────────────────────────────────────────
    // Talep detayı
    // GET /api/demands/{demand}
    // ─────────────────────────────────────────────────────────
    public function show(Request $request, Demand $demand): JsonResponse
    {
        // Onaysız/reddedilmiş talep, sahibi olmayan hiç kimseye görünmemeli.
        // 403 değil 404 dönüyoruz bilerek: "yetkin yok" mesajı talebin var
        // olduğunu ele verir, "bulunamadı" daha güvenli.
        if ($demand->moderation_status !== 'approved') {
            return response()->json(['message' => 'Talep bulunamadı.'], 404);
        }

        $demand->load(['category', 'user:id,name,phone']);
        $demand->loadCount(['offers' => fn($q) => $q->where('moderation_status', 'approved')]);

        // ÖNEMLİ: bu route public (auth:sanctum middleware'i YOK, misafirler
        // de talep detayını görebilsin diye). $request->user() middleware
        // çalışmadığı için varsayılan guard'a (config/auth.php: 'web', yani
        // çerez/session tabanlı) düşüyor — API isteklerinde çerez olmadığı
        // için bearer token geçerli olsa BİLE her zaman null dönüyordu. Guard'ı
        // açıkça 'sanctum' vererek token'ı doğru şekilde çözüyoruz.
        $user = $request->user('sanctum');

        // Talep sahibinin adı/soyadı — kendisi hariç HERKESE maskelenmiş
        // gider ("Doğrulanmış Alıcı G**** K********" gibi). Ham isim asla
        // API cevabında dışarı sızmaz; frontend owner_masked_name alanını
        // kullanmalı, ne $demand->user->name ne de user objesinin kendisi
        // görünür kalır.
        $demand->owner_masked_name = $demand->user ? self::maskName($demand->user->name) : null;
        $demand->is_own_demand     = $user && $demand->user_id === $user->id;
        $demand->makeHidden('user');

        // Giriş yapmış kullanıcı bu talebin kategorisinde teklif vermeye
        // YETKİLİ mi (capability katmanı — kontör/abonelik kapasitesi DEĞİL,
        // sadece hesap tipinin bu iş kolunda iş yapma izni var mı).
        // Frontend (DemandDetailPage) eskiden bunu kullanıcının eski
        // agent_type ENUM'una (emlakci/galerici) bakarak client-side tahmin
        // ediyordu — AccountTypeGroup sistemine geçilince yeni hesaplarda
        // agent_type boş kaldığından, yetkisi OLAN kullanıcılara bile yanlış
        // "yetkiniz yok" uyarısı gösteriliyordu. Artık gerçek kaynaktan
        // (user_category_permissions) okunuyor — OfferController::store()
        // içindeki hasOfferCapability() kontrolüyle birebir aynı.
        $demand->can_offer_capability = ($user && $demand->category)
            ? app(CategoryAccessService::class)->hasOfferCapability($user, $demand->category)
            : false;

        return response()->json($demand);
    }

    /**
     * "Gökay Karakuş" → "G**** K******". Her kelimenin ilk harfi kalır,
     * kalan harf sayısı kadar yıldız eklenir — talep sahibinin kimliğini
     * gizlerken isim uzunluğu hissini korur (ör. mockup: "G**** K********").
     * Tek harflik kelimeler (baş harf gibi) olduğu gibi bırakılır.
     */
    private static function maskName(?string $name): ?string
    {
        if (!$name) return null;

        $words = preg_split('/\s+/', trim($name));

        return collect($words)->map(function ($word) {
            $len = mb_strlen($word);
            if ($len <= 1) return $word;
            return mb_substr($word, 0, 1) . str_repeat('*', $len - 1);
        })->implode(' ');
    }

    // ─────────────────────────────────────────────────────────
    // Talep oluştur
    // POST /api/buyer/demands
    // ─────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $categoryId = $request->category_id;
        if (!$categoryId && $request->category_slug) {
            $category = Category::where('slug', $request->category_slug)->first();
            if (!$category) {
                return response()->json(['message' => 'Geçersiz kategori.', 'errors' => ['category_slug' => ['Kategori bulunamadı.']]], 422);
            }
            $categoryId = $category->id;
        }

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string|max:2000',
            'district'       => 'nullable|string|max:255',
            'neighborhood'   => 'nullable|string|max:255',
            'min_budget'     => 'nullable|numeric|min:0',
            'max_budget'     => 'nullable|numeric|min:0',
            'features'       => 'nullable|array',
            'duration_hours' => 'nullable|integer|min:0',
            'expires_at'     => 'nullable|date|after:now',
            'min_match_percent' => 'nullable|integer|in:60,80,100',
        ]);

        if (!$categoryId) {
            return response()->json(['message' => 'Kategori zorunludur.', 'errors' => ['category' => ['Kategori seçimi yapılmadı.']]], 422);
        }

        // Konum bilgisi: gayrimenkul talebi zaten kendi il/ilçe/mahalle
        // seçicisinden features.il/ilce/mahalleler dolduruyor. Vasıta gibi
        // kendi lokasyon seçicisi olmayan talep türlerinde ise, kullanıcının
        // profilindeki varsayılan adres (yoksa ilk adres) otomatik kullanılır
        // — böylece il/ilçe filtresi tüm talep türlerinde çalışır.
        $features = $validated['features'] ?? [];
        if (empty($features['il'])) {
            $defaultAddress = $request->user()->addresses()->where('is_default', true)->first()
                ?? $request->user()->addresses()->first();

            if ($defaultAddress) {
                $features['il']   = $defaultAddress->city;
                $features['ilce'] = $defaultAddress->district;
                if ($defaultAddress->neighborhood) {
                    $features['mahalleler'] = [$defaultAddress->neighborhood];
                }
            }
        }
        $validated['features'] = $features;

        // Not: moderation_status buraya elle yazılmıyor — migration'daki
        // default('pending') otomatik atıyor. Agent bildirimi, broadcast ve
        // "talebiniz yayına alındı" bildirimi artık burada DEĞİL,
        // ModerationService::approveDemand() içinde (admin onayladığı an)
        // tetikleniyor.
        $demand = $request->user()->demands()->create([
            ...$validated,
            'category_id' => $categoryId,
            'status'      => 'active',
            'expires_at'  => isset($validated['expires_at'])
                ? \Carbon\Carbon::parse($validated['expires_at'])
                : null,
        ]);

        $demand->load('category');

        return response()->json([
            'message' => 'Talebiniz alındı, incelendikten sonra yayına alınacak.',
            'data'    => $demand,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────
    // Talep iptal et
    // POST /api/buyer/demands/{demand}/cancel
    // ─────────────────────────────────────────────────────────
    public function cancel(Request $request, Demand $demand): JsonResponse
    {
        if (!$demand->isOwnedBy($request->user())) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        if (!$demand->isActive()) {
            return response()->json(['message' => 'Bu talep zaten aktif değil.'], 422);
        }

        $demand->update(['status' => 'cancelled']);

        broadcast(new \App\Events\DemandStatusChanged($demand->fresh()));

        return response()->json(['message' => 'Talep iptal edildi.']);
    }

    // ─────────────────────────────────────────────────────────
    // Kategorileri listele
    // GET /api/categories
    // ─────────────────────────────────────────────────────────
    public function categories(): JsonResponse
    {
        $childScope = function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        };

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with([
                'children'                     => $childScope,
                'children.children'            => $childScope,
                'children.children.children'   => $childScope,
                'children.children.children.children' => $childScope,
            ])
            ->get();

        return response()->json($categories);
    }
}
