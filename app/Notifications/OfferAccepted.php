<?php

namespace App\Notifications;

use App\Models\Offer;

/**
 * Alıcı bir teklifi kabul ettiğinde teklifi VEREN ACENTEYE gönderilir.
 * Tetiklenme yeri: OfferController@accept.
 */
class OfferAccepted extends BaseDemandNotification
{
    public function __construct(public Offer $offer) {}

    public function key(): string
    {
        return 'offer.accepted';
    }

    protected function payload($notifiable): array
    {
        $demand = $this->offer->demand;

        return [
            'title'   => 'Teklifiniz kabul edildi',
            'message' => sprintf(
                'Tebrikler! "%s" talebine verdiğiniz teklif kabul edildi. İletişim başlatıldı.',
                $demand?->title ?? 'İlgili talep'
            ),
            'url'  => '/market/' . $this->offer->demand_id,
            'icon' => 'check-circle',
            'meta' => [
                'demand_id' => $this->offer->demand_id,
                'offer_id'  => $this->offer->id,
                'demand_title' => $demand?->title,
            ],
        ];
    }
}
