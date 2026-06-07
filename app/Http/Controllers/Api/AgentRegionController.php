<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentRegion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentRegionController extends Controller
{
    // GET /api/agent/regions
    public function index(Request $request): JsonResponse
    {
        $regions = $request->user()
            ->regions()
            ->orderBy('city')
            ->orderBy('district')
            ->get();

        return response()->json($regions);
    }

    // POST /api/agent/regions
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city'              => 'required|string|max:100',
            'district'          => 'nullable|string|max:100',
            'neighborhood'      => 'nullable|string|max:100',
            'category_slug'     => 'nullable|string|in:gayrimenkul,vasita',
            'notify_new_demand' => 'boolean',
        ]);

        // Max 10 bölge takibi
        if ($request->user()->regions()->count() >= 10) {
            return response()->json([
                'message' => 'En fazla 10 bölge takip edebilirsiniz.'
            ], 422);
        }

        // Kategori uyum kontrolü
        $agentType = $request->user()->agent_type;
        $slug      = $validated['category_slug'] ?? null;

        if ($slug) {
            $allowed = match($agentType) {
                'emlakci'   => ['gayrimenkul'],
                'galerici'  => ['vasita'],
                'her_ikisi' => ['gayrimenkul', 'vasita'],
                default     => [],
            };
            if (!in_array($slug, $allowed)) {
                return response()->json([
                    'message' => 'Bu kategori için takip yetkiniz yok.'
                ], 422);
            }
        }

        $region = $request->user()->regions()->firstOrCreate(
            [
                'city'          => $validated['city'],
                'district'      => $validated['district']      ?? null,
                'neighborhood'  => $validated['neighborhood']  ?? null,
                'category_slug' => $validated['category_slug'] ?? null,
            ],
            ['notify_new_demand' => $validated['notify_new_demand'] ?? true]
        );

        return response()->json([
            'message' => 'Bölge takibe eklendi.',
            'data'    => $region,
        ], 201);
    }

    // DELETE /api/agent/regions/{region}
    public function destroy(Request $request, AgentRegion $region): JsonResponse
    {
        if ($region->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Yetkisiz.'], 403);
        }

        $region->delete();

        return response()->json(['message' => 'Bölge takipten çıkarıldı.']);
    }

    // PATCH /api/agent/regions/{region}/toggle
    public function toggle(Request $request, AgentRegion $region): JsonResponse
    {
        if ($region->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Yetkisiz.'], 403);
        }

        $region->update(['notify_new_demand' => !$region->notify_new_demand]);

        return response()->json([
            'message' => $region->notify_new_demand ? 'Bildirim açıldı.' : 'Bildirim kapatıldı.',
            'data'    => $region,
        ]);
    }
}
