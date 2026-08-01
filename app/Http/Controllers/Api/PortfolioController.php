<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CloseOffersForSoldPortfolioItem;
use App\Models\PortfolioDocument;
use App\Models\PortfolioImage;
use App\Models\PortfolioItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PortfolioController extends Controller
{
    // ────────────────────────────────────────────────
    // PORTFOLIO ITEMS
    // ────────────────────────────────────────────────

    /**
     * GET /api/agent/portfolio
     * Liste — images eager load ile kapak URL'i, documents count ile badge
     */
    public function index(Request $request): JsonResponse
    {
        $query = PortfolioItem::where('user_id', $request->user()->id)
            ->with(['images' => fn($q) => $q->orderBy('sort_order')]) // sadece kapak
            ->withCount('documents')
            // Portföy listesindeki "teklif istatistiği" ikonu/popup'ı için:
            // bu öğeye verilmiş tekliflerden kaçı değerlendirmede, kaç tanesi
            // favorilenmiş, kaçı kabul edilmiş. moderation_status=approved
            // filtresi var çünkü henüz admin onayı bekleyen bir teklifi
            // talep sahibi zaten hiç görmüyor — "değerlendirildi/favorilendi"
            // sadece görünür tekliflerde anlamlı.
            ->withCount([
                'offers as offers_total_count'      => fn($q) => $q->where('moderation_status', 'approved'),
                'offers as offers_reviewing_count'  => fn($q) => $q->where('moderation_status', 'approved')->where('status', 'reviewing'),
                'offers as offers_favorited_count'  => fn($q) => $q->where('moderation_status', 'approved')->where('is_favorited', true),
                'offers as offers_accepted_count'   => fn($q) => $q->where('status', 'accepted'),
            ])
            ->latest();

        if ($request->type)        $query->byType($request->type);
        if ($request->status)      $query->where('status', $request->status);
        if ($request->category_id) $query->where('category_id', $request->category_id);

        return response()->json($query->paginate(20));
    }

    /**
     * GET /api/agent/portfolio/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Tek sorgu ile tüm istatistikler
        $rows = PortfolioItem::where('user_id', $userId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(status = "available") as available,
                SUM(status = "sold")      as sold,
                SUM(status = "reserved")  as reserved
            ')
            ->first();

        $byType = PortfolioItem::where('user_id', $userId)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return response()->json([
            'total'     => (int) $rows->total,
            'available' => (int) $rows->available,
            'sold'      => (int) $rows->sold,
            'reserved'  => (int) $rows->reserved,
            'by_type'   => $byType,
        ]);
    }

    /**
     * POST /api/agent/portfolio  VE  POST /api/my-portfolio (aynı metot,
     * iki route'a birden bağlı — bkz. routes/api.php).
     *
     * BİRLEŞTİRİLDİ: Eskiden store() (type bazlı, agent-only, limitsiz)
     * ve storeMine() (category_id bazlı, grup limitli) iki ayrı metottu.
     * Artık TEK metot, TEK yetki kaynağı (CategoryAccessService) kullanıyor
     * — kullanıcı agent de olsa bireysel de olsa aynı kod yolundan geçiyor,
     * fark sadece user_category_permissions'taki limit değeri (agent'lar
     * için genelde null/sınırsız, bireysel için sayılı).
     *
     * Geriye dönük uyumluluk: eski VehicleFormPage/RealEstateFormPage
     * hâlâ "type" (vasita/gayrimenkul/elektronik) gönderiyor olabilir,
     * yeni PortfolioCategoryPage "category_id" gönderiyor. İkisi de kabul
     * edilir; "type" gelirse slug üzerinden kategoriye çevrilir.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'type'        => 'nullable|string|in:vasita,gayrimenkul,elektronik',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price'       => 'nullable|numeric|min:0',
            'status'      => 'nullable|in:available,reserved,sold',
            'features'    => 'nullable|array',
            'district'    => 'nullable|string|max:255',
        ]);

        if (empty($validated['category_id']) && empty($validated['type'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'category_id' => 'Kategori belirtilmelidir.',
            ]);
        }

        $category = !empty($validated['category_id'])
            ? \App\Models\Category::findOrFail($validated['category_id'])
            : \App\Models\Category::where('slug', $validated['type'])->firstOrFail();

        $user = $request->user();

        if (!app(\App\Services\CategoryAccessService::class)->canAddPortfolio($user, $category)) {
            return response()->json([
                'message' => 'Bu kategoride portföy ekleme hakkınız yok ya da limitiniz doldu.',
                'code'    => 'PORTFOLIO_LIMIT_REACHED',
            ], 403);
        }

        $item = PortfolioItem::create([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'price'       => $validated['price'] ?? null,
            'features'    => $validated['features'] ?? null,
            'district'    => $validated['district'] ?? null,
            'user_id'     => $user->id,
            'category_id' => $category->id,
            // Eski düz "type" alanı geriye dönük uyum için kategorinin KÖK
            // atasının slug'ından türetiliyor (vasita/gayrimenkul/elektronik
            // gibi genel bucket) — $category->slug DEĞİL, çünkü category_id
            // artık spesifik bir YAPRAK kategori olabilir (ör. "Otomobil"),
            // ve vitrin/istatistik tarafındaki byType() filtreleri hâlâ bu
            // 3 genel bucket'a göre çalışıyor.
            'type'        => $category->root()->slug,
            'status'      => $validated['status'] ?? 'available',
        ]);

        return response()->json([
            'message' => 'Portföy kalemi eklendi.',
            'data'    => $item,
        ], 201);
    }

    /**
     * GET /api/agent/portfolio/{item}
     * Detail — tüm images ve documents eager load
     */
    public function show(Request $request, PortfolioItem $item): JsonResponse
    {
        if ($item->user_id !== $request->user()->id)
            return response()->json(['message' => 'Yetkisiz işlem.'], 403);

        $item->load([
            'images'    => fn($q) => $q->orderBy('sort_order'),
            'documents' => fn($q) => $q->latest(),
        ]);

        return response()->json(['data' => $item]);
    }

    /**
     * PUT /api/agent/portfolio/{item}
     */
    public function update(Request $request, PortfolioItem $item): JsonResponse
    {
        if ($item->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price'       => 'nullable|numeric|min:0',
            'status'      => 'nullable|in:available,reserved,sold',
            'features'    => 'nullable|array',
            'district'    => 'nullable|string|max:255',
        ]);

        // Kilit: sadece içerik alanları (başlık/fiyat/açıklama/özellik/ilçe)
        // değişiyorsa ve aktif (pending/reviewing) teklif varsa engelle.
        // "status: sold" geçişi bu kilide takılmaz — aşağıda ayrıca ele alınır.
        $isContentChange = collect($validated)->except('status')->isNotEmpty();

        if ($isContentChange && $item->hasActiveOffers()) {
            $count = $item->offers()->whereIn('status', ['pending', 'reviewing'])->count();
            return response()->json([
                'message' => "Bu araç için {$count} aktif teklif var. Düzenlemeden önce tüm teklifleri geri çekmelisiniz.",
                'code'    => 'PORTFOLIO_LOCKED_BY_OFFERS',
            ], 422);
        }

        // Satıldı olarak işaretleniyorsa: sold_at damgala + bu araca bağlı,
        // henüz karara bağlanmamış (pending/reviewing) tüm teklifleri KAPAT.
        //
        // ÖNEMLİ — ölçeklenebilirlik: burada teklif sayısı ne olursa olsun
        // TEK bir bulk UPDATE çalışır (N adet sorgu atılmaz). Bildirim
        // gönderimi ise HTTP isteğinin dışına, arka planda kendi kendine
        // chunk'layan CloseOffersForSoldPortfolioItem job'ına devredilir —
        // istek anında döner, agent beklemez.
        if (($validated['status'] ?? null) === 'sold' && $item->status !== 'sold') {
            $validated['sold_at'] = now();

            $affected = $item->offers()
                ->whereIn('status', ['pending', 'reviewing'])
                ->update([
                    'status'          => 'rejected',
                    'rejected_reason' => 'sold_elsewhere',
                ]);

            if ($affected > 0) {
                CloseOffersForSoldPortfolioItem::dispatch($item->id)
                    ->onQueue('notifications');
            }
        }

        $item->update($validated);

        return response()->json([
            'message' => 'Güncellendi.',
            'data'    => $item->fresh()->load(['images', 'documents']),
        ]);
    }

    /**
     * DELETE /api/agent/portfolio/{item}
     */
    public function destroy(Request $request, PortfolioItem $item): JsonResponse
    {
        if ($item->user_id !== $request->user()->id)
            return response()->json(['message' => 'Yetkisiz işlem.'], 403);

        // Storage'dan tüm dosyaları sil (images + documents)
        foreach ($item->images as $img) {
            Storage::disk('public')->delete($img->path);
        }
        foreach ($item->documents as $doc) {
            Storage::disk('public')->delete($doc->path);
        }

        $item->delete(); // cascadeOnDelete ile tablo kayıtları silinir

        return response()->json(['message' => 'Silindi.']);
    }

    // ────────────────────────────────────────────────
    // IMAGES
    // ────────────────────────────────────────────────

    /**
     * POST /api/agent/portfolio/{item}/images
     */
    public function uploadImages(Request $request, PortfolioItem $item): JsonResponse
    {
        if ($item->user_id !== $request->user()->id)
            return response()->json(['message' => 'Yetkisiz işlem.'], 403);

        $request->validate([
            'images'   => 'required|array|max:20',
            'images.*' => 'required|image|mimes:jpeg,jpg,png,webp,heic|max:5120', // 5MB
        ]);

        $currentMax = $item->images()->max('sort_order') ?? -1;
        $hasImages  = $item->images()->exists();
        $created    = [];

        foreach ($request->file('images') as $i => $file) {
            $path = $file->store("portfolio/images/{$item->id}", 'public');
            $currentMax++;

            $img = $item->images()->create([
                'path'       => $path,
                'url'        => Storage::url($path),
                'mime_type'  => $file->getMimeType(),
                'size'       => $file->getSize(),
                'sort_order' => $currentMax,
                'is_cover'   => !$hasImages && $i === 0, // ilk yüklenen ilk resim kapak olur
            ]);

            $created[] = $img;
            $hasImages  = true;
        }

        return response()->json([
            'message' => count($created) . ' resim yüklendi.',
            'images'  => $created,
        ]);
    }

    /**
     * DELETE /api/agent/portfolio/{item}/images/{image}
     */
    public function deleteImage(Request $request, PortfolioItem $item, PortfolioImage $image): JsonResponse
    {
        if ($item->user_id !== $request->user()->id || $image->portfolio_item_id !== $item->id)
            return response()->json(['message' => 'Yetkisiz işlem.'], 403);

        $wasCover = $image->is_cover;

        Storage::disk('public')->delete($image->path);
        $image->delete();

        // Kapak silindiyse bir sonrakini kapak yap
        if ($wasCover) {
            $item->images()->orderBy('sort_order')->first()?->update(['is_cover' => true]);
        }

        return response()->json([
            'message' => 'Resim silindi.',
            'images'  => $item->images()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * POST /api/agent/portfolio/{item}/images/bulk-delete
     */
    public function bulkDeleteImages(Request $request, PortfolioItem $item): JsonResponse
    {
        if ($item->user_id !== $request->user()->id)
            return response()->json(['message' => 'Yetkisiz işlem.'], 403);

        $request->validate(['ids' => 'required|array|min:1']);

        $images = $item->images()->whereIn('id', $request->ids)->get();
        $hadCover = $images->where('is_cover', true)->isNotEmpty();

        foreach ($images as $img) {
            Storage::disk('public')->delete($img->path);
            $img->delete();
        }

        // Kapak silindiyse kalan ilk resmi kapak yap
        if ($hadCover) {
            $item->images()->orderBy('sort_order')->first()?->update(['is_cover' => true]);
        }

        return response()->json([
            'message' => count($images) . ' resim silindi.',
            'images'  => $item->images()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * PATCH /api/agent/portfolio/{item}/images/{image}/cover
     * Kapak fotoğrafı değiştir
     */
    public function setCover(Request $request, PortfolioItem $item, PortfolioImage $image): JsonResponse
    {
        if ($item->user_id !== $request->user()->id || $image->portfolio_item_id !== $item->id)
            return response()->json(['message' => 'Yetkisiz işlem.'], 403);

        // Transaction ile güvenli güncelleme
        DB::transaction(function () use ($item, $image) {
            $item->images()->update(['is_cover' => false]);
            $image->update(['is_cover' => true]);
        });

        return response()->json(['message' => 'Kapak fotoğrafı güncellendi.']);
    }

    /**
     * POST /api/agent/portfolio/{item}/images/reorder
     * Sıralama güncelle
     */
    public function reorderImages(Request $request, PortfolioItem $item): JsonResponse
    {
        if ($item->user_id !== $request->user()->id)
            return response()->json(['message' => 'Yetkisiz işlem.'], 403);

        $request->validate([
            'order'    => 'required|array',
            'order.*'  => 'integer|exists:portfolio_images,id',
        ]);

        DB::transaction(function () use ($request, $item) {
            foreach ($request->order as $sortOrder => $imageId) {
                $item->images()->where('id', $imageId)->update(['sort_order' => $sortOrder]);
            }
        });

        return response()->json(['message' => 'Sıralama güncellendi.']);
    }

    // ────────────────────────────────────────────────
    // DOCUMENTS
    // ────────────────────────────────────────────────

    /**
     * POST /api/agent/portfolio/{item}/documents
     */
    public function uploadDocuments(Request $request, PortfolioItem $item): JsonResponse
    {
        if ($item->user_id !== $request->user()->id)
            return response()->json(['message' => 'Yetkisiz işlem.'], 403);

        $request->validate([
            'documents'   => 'required|array|max:10',
            'documents.*' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,heic,doc,docx,xls,xlsx|max:10240',
        ]);

        $created = [];

        foreach ($request->file('documents') as $file) {
            // Görseller için 5MB, diğerleri 10MB
            $isImage  = str_starts_with($file->getMimeType(), 'image/');
            $maxBytes = $isImage ? 5 * 1024 * 1024 : 10 * 1024 * 1024;

            if ($file->getSize() > $maxBytes) {
                $limit = $isImage ? '5MB' : '10MB';
                return response()->json([
                    'message' => "{$file->getClientOriginalName()} dosyası {$limit} limitini aşıyor.",
                ], 422);
            }

            $path = $file->store("portfolio/docs/{$item->id}", 'public');

            $doc = $item->documents()->create([
                'uploaded_by' => $request->user()->id,
                'file_name'   => $file->getClientOriginalName(),
                'path'        => $path,
                'url'         => config('app.url') . Storage::url($path),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'label'       => $request->input('label'),
                'status'      => 'pending', // admin onayı bekliyor
            ]);

            $created[] = $doc;
        }

        return response()->json([
            'message'   => count($created) . ' belge yüklendi, incelemeye alındı.',
            'documents' => $created,
        ]);
    }

    /**
     * DELETE /api/agent/portfolio/{item}/documents/{document}
     */
    public function deleteDocument(Request $request, PortfolioItem $item, PortfolioDocument $document): JsonResponse
    {
        if ($item->user_id !== $request->user()->id || $document->portfolio_item_id !== $item->id)
            return response()->json(['message' => 'Yetkisiz işlem.'], 403);

        Storage::disk('public')->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Belge silindi.']);
    }

    /**
     * POST /api/agent/portfolio/{item}/documents/bulk-delete
     */
    public function bulkDeleteDocuments(Request $request, PortfolioItem $item): JsonResponse
    {
        if ($item->user_id !== $request->user()->id)
            return response()->json(['message' => 'Yetkisiz işlem.'], 403);

        $request->validate(['ids' => 'required|array|min:1']);

        $docs = $item->documents()->whereIn('id', $request->ids)->get();

        foreach ($docs as $doc) {
            Storage::disk('public')->delete($doc->path);
            $doc->delete();
        }

        return response()->json(['message' => count($docs) . ' belge silindi.']);
    }

    /**
     * GET /api/portfolio/available-categories  (bireysel + uzman ortak)
     *
     * Giriş yapmış kullanıcının user_category_permissions'ına (tek gerçek
     * yetki kaynağı) bağlı kategorileri, kalan limitiyle birlikte döner.
     */
    public function availableCategories(Request $request): JsonResponse
    {
        $user = $request->user();

        $permissions = \App\Models\UserCategoryPermission::where('user_id', $user->id)
            ->where('can_add_portfolio', true)
            ->with('category')
            ->get();

        $categories = $permissions
            ->filter(fn($perm) => $perm->category !== null)
            ->map(function ($perm) use ($user) {
                $category = $perm->category;
                $current  = PortfolioItem::where('user_id', $user->id)
                    ->where('category_id', $category->id)
                    ->count();

                return [
                    'id'             => $category->id,
                    'name'           => $category->name,
                    'slug'           => $category->slug,
                    'icon'           => $category->icon,
                    'form_component' => $category->form_component, // null = jenerik form (PortfolioCategoryPage)
                    'form_schema'    => $category->form_schema,    // jenerik formda dinamik alanlar (DynamicCategoryFields)
                    'limit'          => $perm->portfolio_limit,   // null = sınırsız
                    'current' => $current,
                    'can_add' => is_null($perm->portfolio_limit) || $current < $perm->portfolio_limit,
                ];
            });

        return response()->json(['data' => $categories->values()]);
    }

    // ────────────────────────────────────────────────
    // PUBLIC — Vitrin
    // ────────────────────────────────────────────────

    /**
     * GET /api/portfolio/featured
     */
    public function featured(Request $request): JsonResponse
    {
        $type  = $request->query('type');
        $il    = $request->query('il');
        $ilce  = $request->query('ilce');
        $limit = min((int) ($request->query('limit', 8)), 48);

        $query = PortfolioItem::with([
            'user:id,name,company_name,agent_type',
            'images' => fn($q) => $q->orderBy('sort_order'),
        ])
            ->where('status', 'available')
            ->where('moderation_status', 'approved')
            ->whereNotNull('price')
            ->latest();

        if ($type) $query->byType($type);

        // Vitrin sayfasındaki İl/İlçe filtresi — konum, gayrimenkul ve
        // vasıta portföylerinde de features.il/ilce içinde tutuluyor
        // (bkz. RealEstateFormPage/VehicleFormPage), serbest metin
        // `district` sütunu yedek olarak taranıyor.
        if ($il) {
            $query->where(function ($q) use ($il) {
                $q->where('features->il', $il)
                    ->orWhere('district', 'like', "%{$il}%");
            });
        }

        if ($ilce) {
            $query->where(function ($q) use ($ilce) {
                $q->where('features->ilce', $ilce)
                    ->orWhere('district', 'like', "%{$ilce}%");
            });
        }

        $items = $query->limit($limit)->get()->map(fn($item) => [
            'id'         => $item->id,
            'type'       => $item->type,
            'title'      => $item->title,
            'price'      => $item->price,
            'district'   => $item->district,
            'features'   => $item->features,
            'cover_url'  => $item->images()->orderBy('sort_order')->first()?->url,
            'agent_name' => $item->user?->company_name ?: $item->user?->name,
            'agent_type' => $item->user?->agent_type,
        ]);

        return response()->json($items);
    }

    /**
     * GET /api/portfolio/{item}
     * Public ilan detayı — vitrin modalındaki fotoğraf galerisi için.
     * /agent/portfolio/{item}'dan farklı olarak auth/sahiplik kontrolü YOK,
     * bunun yerine sadece yayında olan (available + approved) ilanlar
     * gösterilir. Belgeler (documents) burada dönmez — onlar acenteye özel.
     */
    public function showPublic(PortfolioItem $item): JsonResponse
    {
        if ($item->status !== 'available' || $item->moderation_status !== 'approved') {
            return response()->json(['message' => 'İlan bulunamadı.'], 404);
        }

        $item->loadMissing('user:id,name,company_name,agent_type');

        // Dikkat: $item->images (magic property) yerine bilerek $item->images()
        // (relation metodu) kullanılıyor. portfolio_items tablosunda "images"
        // adında bir kolon varsa, magic property o kolonu (null) döndürüp
        // ilişkiyi gölgeler — bu yüzden ->map() null üzerinde patlıyordu.
        $images = $item->images()->orderBy('sort_order')->get(['id', 'url']);

        return response()->json([
            'id'         => $item->id,
            'type'       => $item->type,
            'title'      => $item->title,
            'price'      => $item->price,
            'district'   => $item->district,
            'features'   => $item->features,
            'images'     => $images->map(fn($img) => [
                'id'  => $img->id,
                'url' => $img->url,
            ]),
            'agent_name' => $item->user?->company_name ?: $item->user?->name,
            'agent_type' => $item->user?->agent_type,
        ]);
    }
}
