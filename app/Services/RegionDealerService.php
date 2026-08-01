<?php

namespace App\Services;

use App\Models\DealerApplication;
use App\Models\DealerRevenueShare;
use App\Models\Payment;
use App\Models\RegionDealer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * İl/ilçe bayilik sisteminin TEK giriş noktası. Üç sorumluluğu var:
 *
 *   1) BÖLGE ÇÖZÜMLEME — bir il/ilçe için hangi bayi yetkili (ilçe bayisi
 *      varsa öncelikli, yoksa il bayisi).
 *   2) SORGU SCOPE'U — Filament moderasyon tablolarını (Talep/Teklif) bir
 *      dealer kullanıcısının SADECE kendi bölgesindeki kayıtları görmesi
 *      için filtreler.
 *   3) GELİR PAYI — başarılı bir ödemeden (abonelik/kontör), ödemeyi yapan
 *      uzmanın kayıtlı iline göre aktif bir il bayisi varsa payını hesaplayıp
 *      DealerRevenueShare kaydı oluşturur.
 *
 * NOT: ilçe bayisinin ayrı bir gelir payı YOK (kullanıcı kararı) — %30 her
 * zaman il bayisine gider, ilçe ataması sadece talep/teklif onay YETKİSİNİ
 * o ilçe için il bayisinden alıp ilçe bayisine devreder.
 */
class RegionDealerService
{
    /** Bu ilin aktif il-seviyeli bayisi (varsa). */
    public function activeIlDealer(string $il): ?RegionDealer
    {
        return RegionDealer::active()
            ->where('region_type', 'il')
            ->where('il', $il)
            ->first();
    }

    /** Bu il+ilçenin aktif ilçe-seviyeli bayisi (varsa). */
    public function activeIlceDealer(string $il, string $ilce): ?RegionDealer
    {
        return RegionDealer::active()
            ->where('region_type', 'ilce')
            ->where('il', $il)
            ->where('ilce', $ilce)
            ->first();
    }

    /**
     * Bir talep/teklifin bölgesi için kim yetkili — ilçe bayisi varsa o,
     * yoksa il bayisi, o da yoksa null (kimseye devredilmemiş, sadece
     * admin/genel merkez görür).
     */
    public function resolveDealerForRegion(?string $il, ?string $ilce): ?RegionDealer
    {
        if (!$il) {
            return null;
        }

        if ($ilce) {
            $ilceDealer = $this->activeIlceDealer($il, $ilce);
            if ($ilceDealer) {
                return $ilceDealer;
            }
        }

        return $this->activeIlDealer($il);
    }

    /** Bu ile bağlı, kendi aktif ilçe bayisi olan ilçelerin listesi (il bayisinin scope'undan hariç tutulacak). */
    public function delegatedIlceler(string $il): array
    {
        return RegionDealer::active()
            ->where('region_type', 'ilce')
            ->where('il', $il)
            ->pluck('ilce')
            ->all();
    }

    /**
     * Bir dealer kullanıcısının Demand moderasyon sorgusunu kendi
     * bölgesine göre filtreler. Admin bu metoda hiç girmez (Resource
     * tarafında admin ayrıca kontrol ediliyor).
     *
     * dealer_staff kullanıcıları da buraya girer — kendi region_dealers
     * kaydı yoktur, ama DealerStaff::regionDealer() üzerinden sahibinin
     * bölgesini miras alır, departmanına göre (galeri/emlak/hepsi)
     * kategori bazında ayrıca daralır (bkz. DealerStaff::departmentCategoryIds).
     *
     * NOT: sadece demands.features->il / ->ilce (JSON) üzerinden
     * eşleştiriyor — eski/legacy `district` serbest metin alanına
     * dayalı kayıtlar (varsa) bu scope'a girmez, bilinen bir sınırlama.
     */
    public function scopeDemandQueryForDealer(Builder $query, User $user): Builder
    {
        $assignments      = $user->regionDealerAssignments()->active()->where('can_approve_demands', true)->get();
        $staffMemberships = $this->activeStaffMembershipsFor($user, 'can_approve_demands');

        if ($assignments->isEmpty() && $staffMemberships->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($assignments, $staffMemberships) {
            foreach ($assignments as $a) {
                if ($a->isIlce()) {
                    $q->orWhere(function (Builder $qq) use ($a) {
                        $qq->where('features->il', $a->il)->where('features->ilce', $a->ilce);
                    });
                    continue;
                }

                // İl bayisi: bu ile ait TÜM kayıtlar, ama kendi aktif ilçe
                // bayisi olan ilçeler hariç (o ilçeler devredilmiş).
                $delegated = $this->delegatedIlceler($a->il);
                $q->orWhere(function (Builder $qq) use ($a, $delegated) {
                    $qq->where('features->il', $a->il);
                    if (!empty($delegated)) {
                        $qq->whereNotIn('features->ilce', $delegated);
                    }
                });
            }

            foreach ($staffMemberships as $staff) {
                $this->applyStaffRegionCondition($q, $staff, function (Builder $qq, $a) {
                    $qq->where('features->il', $a->il)->where('features->ilce', $a->ilce);
                }, function (Builder $qq, $a, array $delegated) {
                    $qq->where('features->il', $a->il);
                    if (!empty($delegated)) {
                        $qq->whereNotIn('features->ilce', $delegated);
                    }
                }, 'category_id');
            }
        });
    }

    /** Aynı mantık, Offer için — demand ilişkisi üzerinden. */
    public function scopeOfferQueryForDealer(Builder $query, User $user): Builder
    {
        $assignments      = $user->regionDealerAssignments()->active()->where('can_approve_offers', true)->get();
        $staffMemberships = $this->activeStaffMembershipsFor($user, 'can_approve_offers');

        if ($assignments->isEmpty() && $staffMemberships->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('demand', function (Builder $dq) use ($assignments, $staffMemberships) {
            $dq->where(function (Builder $q) use ($assignments, $staffMemberships) {
                foreach ($assignments as $a) {
                    if ($a->isIlce()) {
                        $q->orWhere(function (Builder $qq) use ($a) {
                            $qq->where('features->il', $a->il)->where('features->ilce', $a->ilce);
                        });
                        continue;
                    }

                    $delegated = $this->delegatedIlceler($a->il);
                    $q->orWhere(function (Builder $qq) use ($a, $delegated) {
                        $qq->where('features->il', $a->il);
                        if (!empty($delegated)) {
                            $qq->whereNotIn('features->ilce', $delegated);
                        }
                    });
                }

                foreach ($staffMemberships as $staff) {
                    $this->applyStaffRegionCondition($q, $staff, function (Builder $qq, $a) {
                        $qq->where('features->il', $a->il)->where('features->ilce', $a->ilce);
                    }, function (Builder $qq, $a, array $delegated) {
                        $qq->where('features->il', $a->il);
                        if (!empty($delegated)) {
                            $qq->whereNotIn('features->ilce', $delegated);
                        }
                    }, 'category_id');
                }
            });
        });
    }

    /**
     * Bir kullanıcının aktif departman personeli üyelikleri — sahibi
     * (regionDealer) aktif ve ilgili onay yetkisi ($permissionColumn:
     * 'can_approve_demands'/'can_approve_offers') açık olanlar. Admin/dealer
     * kullanıcılarında (dealerStaffMemberships ilişkisi hep boş) bu her
     * zaman boş bir koleksiyon döner, ekstra kontrol gerekmez.
     */
    private function activeStaffMembershipsFor(User $user, string $permissionColumn)
    {
        return $user->dealerStaffMemberships()
            ->active()
            ->whereHas('regionDealer', fn (Builder $q) => $q->where('is_active', true)->where($permissionColumn, true))
            ->with('regionDealer')
            ->get();
    }

    /**
     * $staff->regionDealer (sahibinin bölge ataması) için il/ilçe koşulunu
     * $ilceCallback/$ilCallback ile aynı desende $query'e OR olarak ekler,
     * departmanı 'hepsi' değilse ayrıca $categoryColumn üzerinden kategori
     * kısıtı uygular.
     */
    private function applyStaffRegionCondition(Builder $query, $staff, callable $ilceCallback, callable $ilCallback, string $categoryColumn): void
    {
        $a = $staff->regionDealer;

        if (!$a) {
            return;
        }

        $categoryIds = $staff->departmentCategoryIds();

        if ($a->isIlce()) {
            $query->orWhere(function (Builder $qq) use ($a, $ilceCallback, $categoryIds, $categoryColumn) {
                $ilceCallback($qq, $a);
                if (!is_null($categoryIds)) {
                    $qq->whereIn($categoryColumn, $categoryIds);
                }
            });
            return;
        }

        $delegated = $this->delegatedIlceler($a->il);
        $query->orWhere(function (Builder $qq) use ($a, $delegated, $ilCallback, $categoryIds, $categoryColumn) {
            $ilCallback($qq, $a, $delegated);
            if (!is_null($categoryIds)) {
                $qq->whereIn($categoryColumn, $categoryIds);
            }
        });
    }

    /**
     * Başarılı bir ödemeden (abonelik veya kontör — bkz. PaymentService::
     * grantEntitlement) il bayisi payını hesaplar ve kaydeder. Ödemeyi
     * yapan kullanıcının kayıtlı `city` alanı ile eşleşen aktif bir il
     * bayisi yoksa hiçbir şey yapmaz (sessizce atlar — bu normal, her
     * ilde bayi olmak zorunda değil).
     */
    public function recordRevenueShareForPayment(Payment $payment): void
    {
        $user = $payment->user;

        if (!$user || empty($user->city)) {
            return;
        }

        $dealer = $this->activeIlDealer($user->city);

        if (!$dealer) {
            return;
        }

        try {
            DealerRevenueShare::create([
                'region_dealer_id' => $dealer->id,
                'payment_id'       => $payment->id,
                'user_id'          => $user->id,
                'amount'           => $payment->amount,
                'share_percent'    => $dealer->revenue_share_percent,
                'share_amount'     => round($payment->amount * $dealer->revenue_share_percent / 100, 2),
                'status'           => 'pending',
            ]);
        } catch (\Throwable $e) {
            // unique(payment_id) — aynı ödeme için ikinci kez çağrılırsa
            // (ör. PayTR callback tekrar gelirse) burada patlamasın, sessizce logla.
            Log::warning('Bayi gelir payı kaydedilemedi', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bir bayilik başvurusunu onaylar — gerçek RegionDealer kaydını burada
     * OLUŞTURUR (başvuru tablosu tek başına yetki taşımaz). İlçe
     * belirtilmişse ilçe bayiliği, boşsa il bayiliği olur. Aynı bölge için
     * zaten aktif bir bayi varsa (RegionDealerResource formundaki kuralın
     * aynısı) exception fırlatır — çağıran taraf (Filament action) bunu
     * yakalayıp admin'e göstermeli.
     *
     * @throws \Exception aynı bölgede zaten aktif bir bayi varsa veya
     *                     başvuru zaten değerlendirilmişse
     */
    public function approveApplication(DealerApplication $application, User $reviewer): RegionDealer
    {
        if (!$application->isPending()) {
            throw new \Exception('Bu başvuru zaten değerlendirilmiş.');
        }

        $regionType = $application->requestedRegionType();

        if ($regionType === 'il') {
            if ($this->activeIlDealer($application->il)) {
                throw new \Exception('Bu ile zaten aktif bir il bayisi atanmış. Önce mevcut atamayı pasifleştirin.');
            }
        } else {
            if ($this->activeIlceDealer($application->il, $application->ilce)) {
                throw new \Exception('Bu ilçeye zaten aktif bir ilçe bayisi atanmış. Önce mevcut atamayı pasifleştirin.');
            }
        }

        $dealer = RegionDealer::create([
            'user_id'                => $application->user_id,
            'region_type'            => $regionType,
            'il'                     => $application->il,
            'ilce'                   => $application->ilce,
            'revenue_share_percent'  => 30.00,
            'can_approve_demands'    => true,
            'can_approve_offers'     => true,
            'is_active'              => true,
        ]);

        $application->update([
            'status'      => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $dealer;
    }

    public function rejectApplication(DealerApplication $application, User $reviewer, string $reason): void
    {
        if (!$application->isPending()) {
            throw new \Exception('Bu başvuru zaten değerlendirilmiş.');
        }

        $application->update([
            'status'      => 'rejected',
            'admin_note'  => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }
}
