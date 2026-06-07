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
    // Tüm planları listele
    // GET /api/subscription/plans
    // Auth: Yok (herkes görebilir)
    // ─────────────────────────────────────────────────────────
    public function plans(): JsonResponse
    {
        $plans = collect(SubscriptionService::PLANS)
            ->map(fn($plan, $key) => [
                'id'          => $key,
                'label'       => $plan['label'],
                'price'       => $plan['price'],
                'offer_limit' => $plan['offer_limit'] === 0 ? 'Sınırsız' : $plan['offer_limit'],
            ])
            ->values();

        return response()->json(['plans' => $plans]);
    }

    // ─────────────────────────────────────────────────────────
    // Plan yükselt / aktif et
    // POST /api/subscription/activate
    // Auth: auth-token + agent.approved
    // Body: { plan: 'basic'|'premium'|'pro', months: 1 }
    // ─────────────────────────────────────────────────────────
    public function activate(Request $request): JsonResponse
    {
        $request->validate([
            'plan'   => 'required|in:basic,premium,pro',
            'months' => 'nullable|integer|min:1|max:12',
        ], [
            'plan.in' => 'Geçerli plan seçin: basic, premium veya pro.',
        ]);

        $user = $request->user();

        // TODO: Ödeme entegrasyonu burada çağrılacak
        // PaymentService::charge($user, $plan, $months);

        $this->subscription->activate(
            $user,
            $request->plan,
            $request->months ?? 1
        );

        return response()->json([
            'message'      => 'Aboneliğiniz aktif edildi.',
            'subscription' => $this->subscription->summary($user->fresh()),
        ]);
    }

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