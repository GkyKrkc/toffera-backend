<?php

namespace App\Notifications;

use App\Models\Offer;

/**
 * Alıcı bir teklifi reddettiğinde teklifi VEREN ACENTEYE gönderilir.
 * Tetiklenme yeri: OfferController@reject.
 */
class OfferRejected extends BaseDemandNotification
{
    public function __construct(public Offer $offer) {}

    public function key(): string
    {
        return 'offer.rejected';
    }

    protected function payload($notifiable): array
    {
        $demand = $this->offer->demand;

        return [
            'title'   => 'Teklifiniz değerlendirildi',
            'message' => sprintf(
                '"%s" talebine verdiğiniz teklif bu kez tercih edilmedi. Diğer taleplere göz atabilirsiniz.',
                $demand?->title ?? 'İlgili talep'
            ),
            'url'  => '/market',
            'icon' => 'x-circle',
            'meta' => [
                'demand_id' => $this->offer->demand_id,
                'offer_id'  => $this->offer->id,
                'demand_title' => $demand?->title,
            ],
        ];
    }
}
