<?php

namespace App\Notifications;

use App\Models\Offer;

/**
 * Acente, KABUL EDİLMİŞ bir teklifinden vazgeçtiğinde talep sahibine
 * gönderilir. Tetiklenme yeri: OfferController@withdraw. Talep bu
 * noktada tekrar 'active' olur, talep sahibi diğer teklifleri
 * değerlendirebilir veya yeni teklif bekleyebilir.
 */
class OfferWithdrawn extends BaseDemandNotification
{
    public function __construct(public Offer $offer) {}

    public function key(): string
    {
        return 'offer.withdrawn';
    }

    protected function payload($notifiable): array
    {
        $demand = $this->offer->demand;

        return [
            'title'   => 'Bir teklif geri çekildi',
            'message' => sprintf(
                '"%s" talebiniz için kabul ettiğiniz teklif, acente tarafından geri çekildi. Talebiniz tekrar yayında.',
                $demand?->title ?? 'Talebiniz'
            ),
            'url'  => '/market/' . $this->offer->demand_id,
            'icon' => 'undo-2',
            'meta' => [
                'demand_id' => $this->offer->demand_id,
                'offer_id'  => $this->offer->id,
                'demand_title' => $demand?->title,
            ],
        ];
    }
}
