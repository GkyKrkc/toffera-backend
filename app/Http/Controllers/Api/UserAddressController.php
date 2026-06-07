<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    // GET /api/user/addresses
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return response()->json($addresses);
    }

    // POST /api/user/addresses
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:50',
            'city'         => 'required|string|max:100',
            'district'     => 'required|string|max:100',
            'neighborhood' => 'nullable|string|max:100',
            'full_address' => 'nullable|string|max:500',
            'is_default'   => 'boolean',
        ]);

        // Maksimum 5 adres
        if ($request->user()->addresses()->count() >= 5) {
            return response()->json(['message' => 'En fazla 5 adres ekleyebilirsiniz.'], 422);
        }

        // Varsayılan yapılacaksa diğerlerini kaldır
        if (!empty($validated['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        // İlk adres otomatik varsayılan
        if ($request->user()->addresses()->count() === 0) {
            $validated['is_default'] = true;
        }

        $address = $request->user()->addresses()->create($validated);

        return response()->json([
            'message' => 'Adres eklendi.',
            'data'    => $address,
        ], 201);
    }

    // PUT /api/user/addresses/{address}
    public function update(Request $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Yetkisiz.'], 403);
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:50',
            'city'         => 'required|string|max:100',
            'district'     => 'required|string|max:100',
            'neighborhood' => 'nullable|string|max:100',
            'full_address' => 'nullable|string|max:500',
            'is_default'   => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json(['message' => 'Adres güncellendi.', 'data' => $address]);
    }

    // DELETE /api/user/addresses/{address}
    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Yetkisiz.'], 403);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        // Silinen varsayılansa bir sonrakini varsayılan yap
        if ($wasDefault) {
            $request->user()->addresses()->oldest()->first()?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Adres silindi.']);
    }

    // PATCH /api/user/addresses/{address}/set-default
    public function setDefault(Request $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Yetkisiz.'], 403);
        }

        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json(['message' => 'Varsayılan adres güncellendi.', 'data' => $address]);
    }
}
