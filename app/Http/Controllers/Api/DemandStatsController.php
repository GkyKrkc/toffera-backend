<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demand;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DemandStatsController extends Controller
{
    // GET /api/demands/stats/cities
    public function cities(): JsonResponse
    {
        $stats = Demand::where('status', 'active')
            ->whereNotNull('district')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get(['district'])
            ->groupBy(function ($d) {
                $parts = explode(',', $d->district);
                return trim(end($parts));
            })
            ->map(fn($group) => $group->count())
            ->sortDesc()
            ->toArray();

        return response()->json($stats);
    }

    // GET /api/demands/stats/summary
    public function summary(): JsonResponse
    {
        $activeDemands = Demand::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        $totalAgents = User::whereHas('roles', fn($q) =>
        $q->where('name', 'agent')
        )->where('status', 'active')->count();

        $avgOfferMinutes = Offer::join('demands', 'offers.demand_id', '=', 'demands.id')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, demands.created_at, offers.created_at)) as avg_mins')
            ->value('avg_mins');

        $avgMins  = $avgOfferMinutes ? round($avgOfferMinutes) : null;
        $avgLabel = $avgMins
            ? ($avgMins < 60 ? "{$avgMins} Dk" : round($avgMins / 60) . " Sa")
            : "—";

        $total     = Demand::count();
        $completed = Demand::where('status', 'completed')->count();
        $rate      = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        return response()->json([
            'active_demands' => $activeDemands,
            'total_agents'   => $totalAgents,
            'avg_offer_time' => $avgLabel,
            'success_rate'   => "%{$rate}",
        ]);
    }
}
