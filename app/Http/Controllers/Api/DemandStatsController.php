<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demand;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemandStatsController extends Controller
{
    public function cities(): JsonResponse
    {
        $stats = Demand::where('status', 'active')
            ->whereNotNull('district')
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get(['district'])
            ->groupBy(fn($d) => trim(end(explode(',', $d->district))))
            ->map(fn($g) => $g->count())
            ->sortDesc()->toArray();

        return response()->json($stats);
    }

    public function summary(Request $request): JsonResponse
    {
        $city     = $request->query('city');
        $district = $request->query('district');

        // Aktif talepler
        $demandQ = Demand::where('status', 'active')
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        if ($district) {
            $demandQ->where('district', 'like', "%{$district}%");
        } elseif ($city) {
            $demandQ->where('district', 'like', "%{$city}%");
        }

        $activeDemands = $demandQ->count();

        // Uzman sayısı
        $agentQ = User::whereHas('roles', fn($q) => $q->where('name', 'agent'))
            ->where('status', 'active');

        if ($city || $district) {
            $agentQ->whereHas('regions', function ($q) use ($city, $district) {
                if ($district) {
                    $q->where('district', 'like', "%{$district}%");
                } elseif ($city) {
                    $q->where('city', 'like', "%{$city}%");
                }
            });
        }

        $totalAgents = $agentQ->count();

        // Ortalama teklif süresi
        $offerQ = Offer::join('demands', 'offers.demand_id', '=', 'demands.id')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, demands.created_at, offers.created_at)) as avg_mins');

        if ($district) {
            $offerQ->where('demands.district', 'like', "%{$district}%");
        } elseif ($city) {
            $offerQ->where('demands.district', 'like', "%{$city}%");
        }

        $avgMins  = round($offerQ->value('avg_mins') ?? 0);
        $avgLabel = $avgMins > 0
            ? ($avgMins < 60 ? "{$avgMins} Dk" : round($avgMins / 60) . " Sa")
            : "—";

        // Başarı oranı
        $totalQ = Demand::query();
        $completedQ = Demand::where('status', 'completed');
        if ($district) {
            $totalQ->where('district', 'like', "%{$district}%");
            $completedQ->where('district', 'like', "%{$district}%");
        } elseif ($city) {
            $totalQ->where('district', 'like', "%{$city}%");
            $completedQ->where('district', 'like', "%{$city}%");
        }
        $total = $totalQ->count();
        $rate  = $total > 0 ? round(($completedQ->count() / $total) * 100, 1) : 0;

        return response()->json([
            'active_demands' => $activeDemands,
            'total_agents'   => $totalAgents,
            'avg_offer_time' => $avgLabel,
            'success_rate'   => "%{$rate}",
        ]);
    }
}
