<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOfferLimit
{
    public function __construct(private SubscriptionService $subscription) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Sadece agent rolündeki kullanıcılar için kontrol
        if (!$user->hasRole('agent')) {
            return $next($request);
        }

        if (!$user->canMakeOffer()) {
            $summary = $this->subscription->summary($user);

            return response()->json([
                'message'          => 'Bu ay için teklif limitinize ulaştınız.',
                'code'             => 'OFFER_LIMIT_REACHED',
                'offers_used'      => $summary['offers_used'],
                'offer_limit'      => $user->offer_limit,
                'plan'             => $user->subscription_plan,
                'upgrade_required' => true,
            ], 403);
        }

        return $next($request);
    }
}