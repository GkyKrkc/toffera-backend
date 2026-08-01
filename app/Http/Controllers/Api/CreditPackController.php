<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillableProduct;
use Illuminate\Http\JsonResponse;

class CreditPackController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // Kontör paketlerini listele (bireysel/uzman olmayan kullanıcılar
    // için — 1 kontör = 1 teklif verme hakkı). Admin panelden Ödeme &
    // Abonelik → Ödenebilir Ürünler'den yönetilir.
    // GET /api/credit-packs/plans
    // Auth: Yok (herkes görebilir)
    // ─────────────────────────────────────────────────────────
    public function plans(): JsonResponse
    {
        $packs = BillableProduct::query()
            ->where('type', 'credit_pack')
            ->where('is_active', true)
            ->orderBy('price')
            ->get()
            ->map(fn ($pack) => [
                'code'          => $pack->code,
                'name'          => $pack->name,
                'price'         => $pack->price,
                'credit_amount' => $pack->credit_amount,
            ])
            ->values();

        return response()->json(['plans' => $packs]);
    }
}
