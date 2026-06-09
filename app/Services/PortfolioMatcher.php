<?php

namespace App\Services;

use App\Models\Demand;
use App\Models\PortfolioItem;
use App\Models\User;
use Illuminate\Support\Collection;

class PortfolioMatcher
{
    // Eşleşme eşiği — bu puanın üstündekiler bildirim alır
    const MATCH_THRESHOLD = 40;

    /**
     * Talebe uygun portföy sahibi acenteleri döner
     */
    public static function findMatchingAgents(Demand $demand): Collection
    {
        // Talep kategorisine göre portföy tipini belirle
        $type = $demand->category?->slug;
        if (!$type) return collect();

        // O kategoride available stoku olan acentelerin portföyleri
        $portfolios = PortfolioItem::where('type', $type)
            ->where('status', 'available')
            ->with('user')
            ->get();

        $matched = collect();

        foreach ($portfolios as $item) {
            // Daha önce bu talep için bildirim gittiyse atla
            if ($item->isNotifiedFor($demand->id)) continue;

            $score = self::calculateScore($item, $demand);

            if ($score >= self::MATCH_THRESHOLD) {
                $matched->push([
                    'agent'  => $item->user,
                    'item'   => $item,
                    'score'  => $score,
                ]);
            }
        }

        // Skora göre sırala
        return $matched->sortByDesc('score')->values();
    }

    /**
     * Eşleşen acenteleri bildirim gönderildi olarak işaretle
     */
    public static function markNotified(Collection $matches, int $demandId): void
    {
        foreach ($matches as $match) {
            $match['item']->markNotifiedFor($demandId);
        }
    }

    /**
     * Puan hesaplama motoru
     */
    private static function calculateScore(PortfolioItem $item, Demand $demand): int
    {
        $score    = 0;
        $features = $demand->features ?? [];
        $pf       = $item->features   ?? [];

        // ── Vasıta eşleşmesi ──────────────────────────────
        if ($item->type === 'vasita') {
            // Marka — en kritik kriter
            if (!empty($pf['marka']) && !empty($features['marka'])) {
                if (strtolower($pf['marka']) === strtolower($features['marka'])) {
                    $score += 40;
                }
            }

            // Model
            if (!empty($pf['model']) && !empty($features['model'])) {
                if (strtolower($pf['model']) === strtolower($features['model'])) {
                    $score += 25;
                }
            }

            // Bütçe uyumu
            if ($item->price && $demand->max_budget) {
                if ($item->price <= $demand->max_budget) {
                    $score += 20;
                } elseif ($item->price <= $demand->max_budget * 1.15) {
                    // %15 tolerans
                    $score += 8;
                }
            }

            // Model yılı
            if (!empty($pf['yil']) && !empty($features['yil'])) {
                if ($pf['yil'] >= $features['yil']) {
                    $score += 10;
                }
            }

            // Yakıt tipi
            if (!empty($pf['yakit']) && !empty($features['yakit'])) {
                if (strtolower($pf['yakit']) === strtolower($features['yakit'])) {
                    $score += 5;
                }
            }
        }

        // ── Gayrimenkul eşleşmesi ─────────────────────────
        if ($item->type === 'gayrimenkul') {
            // Emlak tipi
            if (!empty($pf['tip']) && !empty($features['emlak_tipi'])) {
                if (strtolower($pf['tip']) === strtolower($features['emlak_tipi'])) {
                    $score += 35;
                }
            }

            // Bütçe uyumu
            if ($item->price && $demand->max_budget) {
                if ($item->price <= $demand->max_budget) {
                    $score += 30;
                } elseif ($item->price <= $demand->max_budget * 1.10) {
                    $score += 12;
                }
            }

            // Bölge eşleşmesi
            if (!empty($item->district) && !empty($demand->district)) {
                $itemDistrict   = strtolower(trim($item->district));
                $demandDistrict = strtolower($demand->district);
                if (str_contains($demandDistrict, $itemDistrict)) {
                    $score += 25;
                }
            }

            // Oda sayısı
            if (!empty($pf['oda_sayisi']) && !empty($features['oda_sayisi'])) {
                if ($pf['oda_sayisi'] === $features['oda_sayisi']) {
                    $score += 10;
                }
            }
        }

        // ── Elektronik eşleşmesi ──────────────────────────
        if ($item->type === 'elektronik') {
            // Marka
            if (!empty($pf['marka']) && !empty($features['marka'])) {
                if (strtolower($pf['marka']) === strtolower($features['marka'])) {
                    $score += 40;
                }
            }

            // Model
            if (!empty($pf['model']) && !empty($features['model'])) {
                if (strtolower($pf['model']) === strtolower($features['model'])) {
                    $score += 30;
                }
            }

            // Bütçe
            if ($item->price && $demand->max_budget) {
                if ($item->price <= $demand->max_budget) {
                    $score += 20;
                }
            }

            // Depolama / kapasite
            if (!empty($pf['depolama']) && !empty($features['depolama'])) {
                if ($pf['depolama'] === $features['depolama']) {
                    $score += 10;
                }
            }
        }

        return $score;
    }
}
