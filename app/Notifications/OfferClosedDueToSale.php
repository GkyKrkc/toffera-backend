<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Bir portföy öğesi başka bir alıcıya satıldığı için otomatik reddedilen
 * teklifin sahibine (talep/ilan sahibine) gönderilir.
 *
 * NOT: Mevcut BaseDemandNotification'ın gerçek alan adlarını görmedim,
 * bu yüzden diğer 5 bildirim sınıfınla (NewOfferReceived, OfferAccepted,
 * OfferRejected) aynı iskeleti kullandım. (DemandApproved/DemandMatched daha sonra AppNotification+NotificationType enum yapısına taşınıp silindi.)
 * Onlardan birini paylaşırsan bu sınıfı extends BaseDemandNotification
 * yapıp alan adlarını (title/message/type/action_url gibi) birebir
 * eşleştiririm.
 */
class OfferClosedDueToSale extends Notification implements ShouldQueue
{
    use Queueable;

    protected Offer $offer;

    public function __construct(Offer $offer)
    {
        // İlişkileri (portfolioItem, user/acente) yüklü olarak taşı ki
        // queue'ya serialize edilirken ekstra sorgu gerekmesin.
        $this->offer = $offer->loadMissing(['portfolioItem', 'user', 'demand']);
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        $portfolioTitle = $this->offer->portfolioItem?->title ?? 'İlgilendiğiniz portföy';
        $agentName      = $this->offer->user?->company_name ?: $this->offer->user?->name;

        return [
            'type'        => 'offer_closed_due_to_sale',
            'offer_id'    => $this->offer->id,
            'demand_id'   => $this->offer->demand_id,
            'title'       => 'Teklif Kapandı',
            'message'     => "İlgilendiğiniz \"{$portfolioTitle}\" portföyü, {$agentName} tarafından başka bir alıcıya satıldığı için kapatıldı.",
            'icon'        => 'x-circle', // frontend NotificationsPage.jsx ICONS haritasıyla eşleşmeli
            'url'         => "/market/{$this->offer->demand_id}",
            'reason'      => 'sold_elsewhere',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
