<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Demand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemandController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // Pazaryeri listesi — herkese açık, filtreli
    // GET /api/demands
    // ─────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Demand::active()
            ->with(['category:id,name,slug,icon'])
            ->withCount('offers');

        // Kategori filtresi
        if ($request->category) {
            $query->byCategory($request->category);
        }

        // İlçe filtresi
        if ($request->district) {
            $query->byDistrict($request->district);
        }

        // Bütçe filtresi
        if ($request->min_budget || $request->max_budget) {
            $query->byBudget($request->min_budget, $request->max_budget);
        }

        // Metin arama
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%")
                  ->orWhere('district', 'like', "%{$request->search}%");
            });
        }

        // Sıralama
        match ($request->sort) {
            'oldest'      => $query->oldest(),
            'most_offers' => $query->orderByDesc('offers_count'),
            'budget_desc' => $query->orderByDesc('max_budget'),
            'budget_asc'  => $query->orderBy('min_budget'),
            default       => $query->latest(),
        };

        $demands = $query->paginate(12);

        return response()->json($demands);
    }

    // ─────────────────────────────────────────────────────────
    // Müşterinin kendi talepleri
    // GET /api/buyer/demands
    // ─────────────────────────────────────────────────────────
    public function myDemands(Request $request): JsonResponse
    {
        $demands = Demand::where('user_id', $request->user()->id)
            ->with(['category:id,name,slug'])
            ->withCount('offers')
            ->latest()
            ->paginate(20);

        return response()->json($demands);
    }

    // ─────────────────────────────────────────────────────────
    // Talep detayı
    // GET /api/demands/{demand}
    // ─────────────────────────────────────────────────────────
    public function show(Demand $demand): JsonResponse
    {
        $demand->load(['category', 'offers.user:id,name,company_name,agent_type']);
        $demand->loadCount('offers');

        // user_id'yi frontend için gönder ama kişisel bilgi gönderme
        // Sadece kabul edilen teklif sahibi ilan sahibini görebilir — bu OfferController'da çözüldü

        return response()->json($demand);
    }

    // ─────────────────────────────────────────────────────────
    // Talep oluştur
    // POST /api/buyer/demands
    // ─────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id'  => 'required|exists:categories,id',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'district'     => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'min_budget'   => 'nullable|numeric|min:0',
            'max_budget'   => 'nullable|numeric|min:0|gte:min_budget',
            'features'     => 'nullable|array',
            'expires_at'   => 'nullable|date|after:now',
        ]);

        $demand = $request->user()->demands()->create([
            ...$validated,
            'status'     => 'active',
            'expires_at' => isset($validated['expires_at'])
                ? \Carbon\Carbon::parse($validated['expires_at'])
                : null,
        ]);

        $demand->load('category');

        // Bölge eşleştirme — eşleşen agent'lara SMS bildirimi gönder
        \App\Services\DemandRegionMatcher::notifyAgents($demand);

        // Gerçek zamanlı yeni ilan bildirimi
        $matchingAgents = \App\Services\DemandRegionMatcher::findMatchingAgents($demand);
        $agentIds = $matchingAgents->pluck('id')->toArray();
        if (!empty($agentIds)) {
            broadcast(new \App\Events\NewDemand($demand, $agentIds));
        }
        return response()->json([
            'message' => 'Talep başarıyla oluşturuldu.',
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
        $categories = Category::active()
            ->select('id', 'name', 'slug', 'icon', 'form_schema')
            ->get();

        return response()->json($categories);
    }
}
