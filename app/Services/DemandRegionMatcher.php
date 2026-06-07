<?php

namespace App\Services;

use App\Models\AgentRegion;
use App\Models\Demand;
use Illuminate\Support\Collection;

class DemandRegionMatcher
{
    /**
     * Yeni talep ile eşleşen agent'ları bul
     */
    public static function findMatchingAgents(Demand $demand): Collection
    {
        $categorySlug = $demand->category?->slug;
        $district     = $demand->district ?? '';

        // "Onikişubat, Kahramanmaraş" → city: Kahramanmaraş, district: Onikişubat
        $parts = array_map('trim', explode(',', $district));
        $city  = count($parts) >= 2 ? end($parts)    : $district;
        $dist  = count($parts) >= 2 ? $parts[0]      : null;

        return AgentRegion::where('notify_new_demand', true)
            ->where('city', $city)
            ->where(function ($q) use ($dist) {
                $q->whereNull('district')
                    ->orWhere('district', $dist);
            })
            ->where(function ($q) use ($categorySlug) {
                $q->whereNull('category_slug')
                    ->orWhere('category_slug', $categorySlug);
            })
            ->with('user:id,name,phone,agent_type,status')
            ->get()
            ->filter(fn($r) => $r->user?->status === 'active')
            ->pluck('user')
            ->unique('id');
    }

    /**
     * Eşleşen agent'lara SMS bildirimi gönder
     */
    public static function notifyAgents(Demand $demand): void
    {
        $agents = self::findMatchingAgents($demand);

        foreach ($agents as $agent) {
            try {
                // Mevcut SMS servisini kullan
                app(\App\Services\SmsService::class)->send(
                    $agent->phone,
                    "TOFFERA: {$demand->district} bölgesinde yeni bir {$demand->category?->name} talebi oluşturuldu. Teklif vermek için uygulamayı açın."
                );
            } catch (\Throwable $e) {
                \Log::warning("Bölge bildirimi gönderilemedi", [
                    'agent_id'  => $agent->id,
                    'demand_id' => $demand->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }
}
