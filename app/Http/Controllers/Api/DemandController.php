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
            ->with(['category:id,name,slug,icon', 'user:id,name,company_name'])
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
        $demand->load([
            'category',
            'user:id,name,company_name',
            'offers.user:id,name,company_name',
        ]);
        $demand->loadCount('offers');

        return response()->json($demand);
    }

    // ─────────────────────────────────────────────────────────
    // Talep oluştur
    // POST /api/buyer/demands
    // ─────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'district'    => 'nullable|string|max:100',
            'min_budget'  => 'nullable|numeric|min:0',
            'max_budget'  => 'nullable|numeric|min:0|gte:min_budget',
            'features'    => 'nullable|array',
        ], [
            'category_id.exists' => 'Geçerli bir kategori seçin.',
            'title.required'     => 'Talep başlığı zorunludur.',
            'max_budget.gte'     => 'Maksimum bütçe, minimum bütçeden büyük olmalıdır.',
        ]);

        $demand = Demand::create([
            'user_id'     => $request->user()->id,
            'category_id' => $request->category_id,
            'title'       => $request->title,
            'description' => $request->description,
            'district'    => $request->district,
            'min_budget'  => $request->min_budget,
            'max_budget'  => $request->max_budget,
            'features'    => $request->features,
            'status'      => 'active',
        ]);

        $demand->load('category:id,name,slug');

        return response()->json([
            'message' => 'Talebiniz oluşturuldu.',
            'demand'  => $demand,
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