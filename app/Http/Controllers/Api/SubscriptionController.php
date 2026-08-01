<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscription) {}

    // ─────────────────────────────────────────────────────────
    // Mevcut abonelik durumu
    // GET /api/subscription
    // Auth: auth-token
    // ─────────────────────────────────────────────────────────
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'subscription' => $this->subscription->summary($request->user()),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Tüm planları listele (billable_products tablosundan, admin
    // panelden Ödeme & Abonelik → Ödenebilir Ürünler'den yönetilir)
    // GET /api/subscription/plans
    // Auth: Yok (herkes görebilir)
    // ─────────────────────────────────────────────────────────
    public function plans(): JsonResponse
    {
        $plans = $this->subscription->getActivePlans()
            ->map(fn ($plan) => [
                'code'            => $plan->code,
                'name'            => $plan->name,
                'price'           => $plan->price,
                'offer_quota'     => $plan->offer_quota === null ? 'Sınırsız' : $plan->offer_quota,
                'portfolio_limit' => $plan->unlimited_portfolio ? 'Sınırsız' : $plan->portfolio_limit_override,
                'duration_days'   => $plan->duration_days,
            ])
            ->values();

        return response()->json(['plans' => $plans]);
    }

    // ─────────────────────────────────────────────────────────
    // Abonelik satın alma artık PaymentController::checkout() üzerinden
    // yapılıyor (POST /api/subscription/checkout) — orada bir Payment
    // kaydı açılıp PayTR iframe token'ı dönülüyor. Abonelik, kullanıcı
    // ödemeyi PayTR'da tamamladıktan sonra callback ile aktif ediliyor,
    // burada doğrudan "aktif et" YOK (ödeme almadan aktif etmek admin
    // panelinin işi — AdminController::setSubscription).
    // ─────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────
    // Aboneliği iptal et
    // POST /api/subscription/cancel
    // Auth: auth-token + agent.approved
    // ─────────────────────────────────────────────────────────
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Aktif bir aboneliğiniz bulunmuyor.',
            ], 422);
        }

        $this->subscription->cancel($user);

        return response()->json([
            'message' => 'Aboneliğiniz iptal edildi.',
        ]);
    }
}
