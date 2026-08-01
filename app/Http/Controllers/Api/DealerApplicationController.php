<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DealerApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Giriş yapmış herhangi bir kullanıcının "bayi olmak istiyorum" başvurusu.
 * Onay/red işlemi burada YOK — o admin panelinden (DealerApplicationResource
 * → RegionDealerService::approveApplication/rejectApplication) yapılıyor.
 */
class DealerApplicationController extends Controller
{
    // POST /api/dealer-applications
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->dealerApplications()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'il' => 'Zaten beklemede olan bir bayilik başvurunuz var.',
            ]);
        }

        $data = $request->validate([
            'il'         => 'required|string|max:255',
            'ilce'       => 'nullable|string|max:255',
            'motivation' => 'required|string|min:20|max:2000',
        ], [
            'il.required'         => 'İl seçimi zorunludur.',
            'motivation.required' => 'Kısa bir açıklama yazmanız gerekiyor.',
            'motivation.min'      => 'Açıklamanız en az 20 karakter olmalı.',
        ]);

        $application = $user->dealerApplications()->create([
            'il'         => $data['il'],
            'ilce'       => $data['ilce'] ?? null,
            'motivation' => $data['motivation'],
            'status'     => 'pending',
        ]);

        return response()->json(['data' => $application], 201);
    }

    // GET /api/dealer-applications/me — en son başvurunun durumu
    public function me(Request $request): JsonResponse
    {
        $application = $request->user()
            ->dealerApplications()
            ->latest()
            ->first();

        return response()->json(['data' => $application]);
    }
}
