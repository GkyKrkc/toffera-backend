<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\AgentRegion;
use App\Models\Demand;
use App\Notifications\AppNotification;
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
     * Eşleşen agent'lara bildirim gönder.
     *
     * ÖNCEDEN: app(SmsService::class)->send(...) çağrılıyordu — bu metod
     * SmsService'te HİÇ YOKTU (sadece sendOtp/verifyOtp/canResend var),
     * yani bu çağrı BadMethodCallException fırlatıyordu ve catch bloğu
     * sessizce yutuyordu. Bölge bildirimleri muhtemelen hiç gitmiyordu.
     *
     * ARTIK: AppNotification üzerinden gidiyor — database + broadcast
     * (uygulama içi bildirim çanı) her zaman, SMS DEMAND_MATCHED için
     * varsayılan olarak kapalı (NotificationType::channels()'a bakınız).
     * SMS de istiyorsan enum'da DEMAND_MATCHED'i sms listesine ekleriz.
     */
    public static function notifyAgents(Demand $demand): void
    {
        $agents = self::findMatchingAgents($demand);

        foreach ($agents as $agent) {
            try {
                $agent->notify(new AppNotification(NotificationType::DEMAND_MATCHED, [
                    'demand_id'    => $demand->id,
                    'demand_title' => $demand->title,
                    'action_url'   => "/market/{$demand->id}",
                ]));
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
