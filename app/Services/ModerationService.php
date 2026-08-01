<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Demand;
use App\Models\Offer;
use App\Models\PortfolioDocument;
use App\Models\PortfolioItem;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\NewOfferReceived;

/**
 * Belge / ilan / teklif onay-red işlemlerinin TEK merkezi. Hangi Filament
 * Resource'undan çağrılırsa çağrılsın (mevcut ya da yeni), iş mantığı
 * burada — böylece "onaylandığında ne olmalı" sorusunun cevabı tek yerde,
 * modeller/controller'lar arasında dağılmıyor.
 */
class ModerationService
{
    // ── Belge (PortfolioDocument) ──

    public function approveDocument(PortfolioDocument $document, User $reviewer): void
    {
        $document->update([
            'status'      => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $item = $document->portfolioItem;

        // 'sahiplik_belgesi' onaylandıysa öğenin sahiplik doğrulamasını tetikle.
        if ($document->label === 'sahiplik_belgesi' && $item && !$item->ownership_verified_at) {
            $item->update(['ownership_verified_at' => now()]);
        }

        $item?->user?->notify(new AppNotification(
            NotificationType::DOCUMENT_APPROVED,
            ['document_label' => $document->label ?? $document->file_name]
        ));
    }

    public function rejectDocument(PortfolioDocument $document, User $reviewer, string $reason): void
    {
        $document->update([
            'status'           => 'rejected',
            'reviewed_by'      => $reviewer->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        $document->portfolioItem?->user?->notify(new AppNotification(
            NotificationType::DOCUMENT_REJECTED,
            ['document_label' => $document->label ?? $document->file_name, 'reason' => $reason]
        ));
    }

    // ── İlan (PortfolioItem) ──

    public function approveListing(PortfolioItem $item, User $reviewer): void
    {
        $item->update([
            'moderation_status' => 'approved',
            'moderated_by'      => $reviewer->id,
            'moderated_at'      => now(),
        ]);

        $item->user?->notify(new AppNotification(
            NotificationType::LISTING_APPROVED,
            ['item_title' => $item->title]
        ));
    }

    public function rejectListing(PortfolioItem $item, User $reviewer, string $reason): void
    {
        $item->update([
            'moderation_status' => 'rejected',
            'moderated_by'      => $reviewer->id,
            'moderated_at'      => now(),
            'moderation_note'   => $reason,
        ]);

        $item->user?->notify(new AppNotification(
            NotificationType::LISTING_REJECTED,
            ['item_title' => $item->title, 'reason' => $reason]
        ));
    }
// ── Talep (Demand) ──

    public function approveDemand(Demand $demand, User $reviewer): void
    {
        $demand->update([
            'moderation_status' => 'approved',
            'moderated_by'      => $reviewer->id,
            'moderated_at'      => now(),
        ]);

        $demand->loadMissing('user');

        // Onaylanana kadar ertelenen agent bildirimi + broadcast burada tetiklenir.
        $regionAgents = \App\Services\DemandRegionMatcher::findMatchingAgents($demand);

        $portfolioMatches = \App\Services\PortfolioMatcher::findMatchingAgents($demand);
        if ($portfolioMatches->isNotEmpty()) {
            \App\Services\PortfolioMatcher::markNotified($portfolioMatches, $demand->id);
        }

        $notifiedAgents = collect();
        foreach ($regionAgents as $agent) {
            $notifiedAgents->put($agent->id, $agent);
        }
        foreach ($portfolioMatches as $match) {
            $notifiedAgents->put($match['agent']->id, $match['agent']);
        }

        if ($notifiedAgents->isNotEmpty()) {
            broadcast(new \App\Events\NewDemand($demand, $notifiedAgents->keys()->toArray()));

            foreach ($notifiedAgents as $agent) {
                $agent->notify(new AppNotification(
                    NotificationType::DEMAND_MATCHED,
                    [
                        'demand_id'    => $demand->id,
                        'demand_title' => $demand->title,
                        'action_url'   => "/market/{$demand->id}",
                    ]
                ));
            }
        }

        $demand->user?->notify(new AppNotification(NotificationType::DEMAND_APPROVED, [
            'demand_id'    => $demand->id,
            'demand_title' => $demand->title,
        ]));

        // Herkese açık canlı akış (anasayfa) — talep onaylandığı an, giriş
        // yapmış/yapmamış herkesin ekranındaki listeye en üstte düşsün diye.
        // Yukarıdaki NewDemand'dan farklı: bu private değil, public 'demands'
        // kanalında, agent eşleşmesi şartı yok.
        broadcast(new \App\Events\DemandPublished($demand));
    }

    public function rejectDemand(Demand $demand, User $reviewer, string $reason): void
    {
        $demand->update([
            'moderation_status' => 'rejected',
            'moderated_by'      => $reviewer->id,
            'moderated_at'      => now(),
            'moderation_note'   => $reason,
        ]);

        $demand->user?->notify(new AppNotification(
            NotificationType::DEMAND_REJECTED,
            ['demand_title' => $demand->title, 'reason' => $reason]
        ));
    }
    // ── Teklif (Offer) ──

    /**
     * Teklif onaylanınca: moderation_status='approved' + ARTIK talep
     * sahibine "yeni teklif" bildirimi + broadcast burada tetiklenir
     * (OfferController::store()'dan buraya taşındı — moderasyon
     * bitmeden erken bildirim gitmesin diye).
     */
    public function approveOffer(Offer $offer, User $reviewer): void
    {
        $offer->update([
            'moderation_status' => 'approved',
            'moderated_by'      => $reviewer->id,
            'moderated_at'      => now(),
        ]);

        $offer->loadMissing(['demand.user', 'user', 'portfolioItem.images']);

        if (class_exists(\App\Events\NewOffer::class)) {
            broadcast(new \App\Events\NewOffer($offer))->toOthers();
        }

        // Herkese açık canlı akış (anasayfa) — bu teklif onaylanınca, o
        // talebin görünen teklif sayısı ekranda anlık artsın diye.
        if ($offer->demand) {
            $offersCount = $offer->demand->offers()->where('moderation_status', 'approved')->count();
            broadcast(new \App\Events\DemandOfferCountChanged($offer->demand_id, $offersCount));
        }

        $offer->demand?->user?->notify(new NewOfferReceived($offer));

        $offer->user?->notify(new AppNotification(NotificationType::OFFER_MODERATION_APPROVED, [
            'offer_id'  => $offer->id,
            'demand_id' => $offer->demand_id,
        ]));
    }

    public function rejectOffer(Offer $offer, User $reviewer, string $reason): void
    {
        $offer->update([
            'moderation_status' => 'rejected',
            'moderated_by'      => $reviewer->id,
            'moderated_at'      => now(),
            'moderation_note'   => $reason,
        ]);

        $offer->user?->notify(new AppNotification(
            NotificationType::OFFER_MODERATION_REJECTED,
            ['reason' => $reason]
        ));

        // NOT: teklif reddedilse bile harcanan kontör/hak iade edilmiyor —
        // bu bilinçli bir karar mı (moderasyon reddi = kullanıcı hatası
        // sayılıyor) yoksa iade mi olmalı, netleştirelim.
    }
}
