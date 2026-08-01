<?php

namespace App\Services;

use App\Models\Demand;
use App\Models\PortfolioItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PortfolioMatcher
{
    const MATCH_THRESHOLD = 40;

    /**
     * Bir ACENTENİN, belirli bir talebe teklif verirken seçebileceği
     * UYGUN portföy öğelerini döndürür.
     *
     * Mantık (kullanıcının istediği gibi):
     *   1. Ön filtre — marka/model uyumu OLMAYAN öğeler baştan elenir
     *      (talep BMW arıyorsa, acentenin Fiat'ı listeye hiç girmez).
     *   2. Kalan öğeler calculateScore ile puanlanır ve yüzdeye çevrilir.
     *   3. En yüksek eşleşmeden aşağıya doğru sıralı döner (%100, %95, ...).
     *
     * Emlakta da aynı: emlak tipi (konut/ticari/arsa) + oda uyumu ön filtre.
     *
     * @return Collection<array{item: PortfolioItem, score: int, percent: int}>
     */
    public static function matchingPortfolioForAgent(Demand $demand, int $agentId): Collection
    {
        $type = $demand->category?->slug;
        if (!$type) return collect();

        $items = PortfolioItem::where('user_id', $agentId)
            ->where('type', $type)
            ->where('status', 'available')
            ->where('moderation_status', 'approved')
            ->with(['images' => fn($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->latest()
            ->take(100) // Tek acentenin portföyü pratikte bu kadar büyük olmaz; güvenlik/performans tavanı
            ->get();

        $maxScore = self::maxScoreFor($type);

        return $items
            ->filter(fn($item) => self::passesPrefilter($item, $demand))
            ->map(function ($item) use ($demand, $maxScore) {
                $score = self::calculateScore($item, $demand);
                $percent = $maxScore > 0 ? (int) round(min(100, max(0, $score / $maxScore * 100))) : 0;
                return ['item' => $item, 'score' => $score, 'percent' => $percent];
            })
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Ön filtre — sadece marka/model (vasıta/elektronik) veya
     * emlak tipi (gayrimenkul) uyumlu öğeler geçer.
     * Uyum yoksa öğe listeye hiç girmez.
     */
    private static function passesPrefilter(PortfolioItem $item, Demand $demand): bool
    {
        $features = $demand->features ?? [];
        $pf       = $item->features   ?? [];

        if (in_array($item->type, ['vasita', 'elektronik'], true)) {
            // Talepte marka belirtilmişse, portföy markası eşleşmeli
            if (!empty($features['marka'])) {
                if (empty($pf['marka']) || strtolower($pf['marka']) !== strtolower($features['marka'])) {
                    return false;
                }
            }
            // Talepte model de belirtilmişse, model de eşleşmeli
            if (!empty($features['model'])) {
                if (empty($pf['model']) || strtolower($pf['model']) !== strtolower($features['model'])) {
                    return false;
                }
            }
            return true;
        }

        if ($item->type === 'gayrimenkul') {
            // Emlak tipi eşleşmeli (konut/ticari/arsa)
            if (!empty($features['emlak_tipi'])) {
                if (empty($pf['tip']) || strtolower($pf['tip']) !== strtolower($features['emlak_tipi'])) {
                    return false;
                }
            }
            return true;
        }

        return true;
    }

    /**
     * Kategoriye göre teorik maksimum skor — yüzde hesabı için tavan.
     * calculateScore'daki pozitif puanların toplamıyla hizalı tutulmalı.
     */
    private static function maxScoreFor(string $type): int
    {
        return match ($type) {
            'vasita'      => 100,  // marka40 + model25 + fiyat20 + yil10 + yakit5
            'gayrimenkul' => 100,  // tip35 + fiyat30 + bolge25 + oda10
            'elektronik'  => 100,  // marka40 + model30 + fiyat20 + depolama10
            default       => 100,
        };
    }


    /**
     * Talebe uygun portföy sahibi acenteleri döner.
     * chunkById ile işlenir — tüm portföyler bir anda RAM'e alınmaz.
     * Bildirim kontrolü tek sorguda yapılır (N+1 yok).
     */
    public static function findMatchingAgents(Demand $demand): Collection
    {
        $type = $demand->category?->slug;
        if (!$type) return collect();

        // Bu talep için daha önce bildirim yapılan item ID'lerini TEK sorguda çek
        $notifiedIds = DB::table('portfolio_demand_notifications')
            ->where('demand_id', $demand->id)
            ->pluck('portfolio_item_id')
            ->toArray();

        $matched = collect();

        // 200'er chunk — 10.000 portföy için 50 sorgu, tümü RAM'e değil
        PortfolioItem::where('type', $type)
            ->where('status', 'available')
            ->where('moderation_status', 'approved')
            ->when(!empty($notifiedIds), fn($q) => $q->whereNotIn('id', $notifiedIds))
            ->with(['user:id,name,company_name,agent_type,phone'])
            ->chunkById(200, function ($items) use ($demand, $matched) {
                foreach ($items as $item) {
                    if (!$item->user) continue;

                    $score = self::calculateScore($item, $demand);

                    if ($score >= self::MATCH_THRESHOLD) {
                        $matched->push([
                            'agent' => $item->user,
                            'item'  => $item,
                            'score' => $score,
                        ]);
                    }
                }
            });

        return $matched->sortByDesc('score')->values();
    }

    /**
     * Tek bulk insert ile işaretle — foreach içinde tek tek kayıt yok.
     */
    public static function markNotified(Collection $matches, int $demandId): void
    {
        if ($matches->isEmpty()) return;

        $rows = $matches->map(fn($m) => [
            'portfolio_item_id' => $m['item']->id,
            'demand_id'         => $demandId,
            'created_at'        => now(),
        ])->toArray();

        DB::table('portfolio_demand_notifications')
            ->insertOrIgnore($rows);
    }

    /**
     * Puan hesaplama — aynı mantık, değişmedi
     */
    public static function calculateScore(PortfolioItem $item, Demand $demand): int
    {
        $score    = 0;
        $features = $demand->features ?? [];
        $pf       = $item->features   ?? [];

        if ($item->type === 'vasita') {
            if (!empty($pf['marka']) && !empty($features['marka'])) {
                if (strtolower($pf['marka']) === strtolower($features['marka'])) $score += 40;
            }
            if (!empty($pf['model']) && !empty($features['model'])) {
                if (strtolower($pf['model']) === strtolower($features['model'])) $score += 25;
            }
            if ($item->price && $demand->max_budget) {
                if ($item->price <= $demand->max_budget)            $score += 20;
                elseif ($item->price <= $demand->max_budget * 1.15) $score += 8;
            }
            if (!empty($pf['yil']) && !empty($features['yil'])) {
                if ($pf['yil'] >= $features['yil']) $score += 10;
            }
            if (!empty($pf['yakit']) && !empty($features['yakit'])) {
                if (strtolower($pf['yakit']) === strtolower($features['yakit'])) $score += 5;
            }

            // ── Parça bazlı hasar uyum kontrolü ─────────────
            $parcaDurumlari = $pf['parca_durumlari'] ?? [];
            $boyaliSayisi   = count(array_filter($parcaDurumlari, fn($d) => in_array($d, ['boyali', 'lokal_boyali'])));
            $degisenSayisi  = count(array_filter($parcaDurumlari, fn($d) => $d === 'degisen'));
            $boyaDegTipi    = $features['boya_degisen_tipi'] ?? null;

            if ($boyaDegTipi === 'boyasiz_degisensiz') {
                if ($boyaliSayisi === 0 && $degisenSayisi === 0) $score += 15;
                else $score -= 20;
            }
            if ($boyaDegTipi === 'boya_degisen_olabilir') {
                $kabulBoya    = self::parseParcaSayisi($features['boya_durumu']   ?? null);
                $kabulDegisen = self::parseParcaSayisi($features['degisen_parca'] ?? null);
                if ($kabulBoya    !== null && $boyaliSayisi  <= $kabulBoya)    $score += 8;
                if ($kabulDegisen !== null && $degisenSayisi <= $kabulDegisen) $score += 8;
            }

            // Kabul edilemez parça kontrolü
            $kabulEdilmez = $features['kabul_edilemez'] ?? [];
            $parcaKeyMap  = [
                'Ön Kaput'         => 'on_kaput',
                'Tavan'            => 'tavan',
                'Sağ Ön Çamurluk' => 'sag_on_camurluk',
                'Sol Ön Çamurluk' => 'sol_on_camurluk',
                'Bagaj Kapağı'    => 'bagaj',
                'Direkler (A/B/C)'=> 'direkler',
            ];
            foreach ($kabulEdilmez as $etiket) {
                $parcaKey = $parcaKeyMap[$etiket] ?? null;
                if ($parcaKey && isset($parcaDurumlari[$parcaKey])) {
                    if (in_array($parcaDurumlari[$parcaKey], ['boyali', 'lokal_boyali', 'degisen'])) {
                        $score -= 30;
                    }
                }
            }

            // Ekspertiz & tramer & katılım finansı uyumu
            if (!empty($features['tramer_bilgisi_istiyorum']) && !empty($pf['tramer_bilgisi_paylasiyorum'])) {
                $score += 5;
                if (!empty($features['tramer_limit']) && !empty($pf['tramer_tutari'])) {
                    $score += ((int)$pf['tramer_tutari'] <= (int)$features['tramer_limit']) ? 5 : -10;
                }
            }
            if (!empty($features['eksper_raporu_istiyorum']) && !empty($pf['eksper_raporu_mevcut']))    $score += 5;
            if (!empty($features['katilim_finansi'])          && !empty($pf['katilim_finansi_uyumlu'])) $score += 5;
        }

        if ($item->type === 'gayrimenkul') {
            if (!empty($pf['tip']) && !empty($features['emlak_tipi'])) {
                if (strtolower($pf['tip']) === strtolower($features['emlak_tipi'])) $score += 35;
            }
            if ($item->price && $demand->max_budget) {
                if ($item->price <= $demand->max_budget)            $score += 30;
                elseif ($item->price <= $demand->max_budget * 1.10) $score += 12;
            }
            if (!empty($item->district) && !empty($demand->district)) {
                if (str_contains(strtolower($demand->district), strtolower(trim($item->district)))) {
                    $score += 25;
                }
            }
            if (!empty($pf['oda_sayisi']) && !empty($features['oda_sayisi'])) {
                if ($pf['oda_sayisi'] === $features['oda_sayisi']) $score += 10;
            }
        }

        if ($item->type === 'elektronik') {
            if (!empty($pf['marka']) && !empty($features['marka'])) {
                if (strtolower($pf['marka']) === strtolower($features['marka'])) $score += 40;
            }
            if (!empty($pf['model']) && !empty($features['model'])) {
                if (strtolower($pf['model']) === strtolower($features['model'])) $score += 30;
            }
            if ($item->price && $demand->max_budget) {
                if ($item->price <= $demand->max_budget) $score += 20;
            }
            if (!empty($pf['depolama']) && !empty($features['depolama'])) {
                if ($pf['depolama'] === $features['depolama']) $score += 10;
            }
        }

        return $score;
    }

    /**
     * "1-3 Parça" → 3, "3-5 Parça" → 5, "5+ Parça" → 99
     */
    private static function parseParcaSayisi(?string $str): ?int
    {
        if (!$str) return null;
        if (str_contains($str, '5+'))  return 99;
        if (str_contains($str, '3-5')) return 5;
        if (str_contains($str, '1-3')) return 3;
        return null;
    }
}
