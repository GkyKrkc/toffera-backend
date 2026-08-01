<?php

namespace App\Notifications;

use App\Models\Offer;

/**
 * Alıcının talebine yeni bir teklif geldiğinde ALICIYA gönderilir.
 * Tetiklenme yeri: OfferController@store (teklif oluşturulduktan sonra).
 */
class NewOfferReceived extends BaseDemandNotification
{
    public function __construct(public Offer $offer) {}

    public function key(): string
    {
        return 'offer.received';
    }

    protected function payload($notifiable): array
    {
        $demand = $this->offer->demand;

        return [
            'title'   => 'Yeni teklif aldınız',
            'message' => sprintf(
                '"%s" talebinize %s ₺ tutarında yeni bir teklif geldi.',
                $demand?->title ?? 'Talebiniz',
                number_format((float) $this->offer->price, 0, ',', '.')
            ),
            'url'  => '/market/' . $this->offer->demand_id,
            'icon' => 'inbox',
            'meta' => [
                'demand_id' => $this->offer->demand_id,
                'offer_id'  => $this->offer->id,
                'price'     => $this->offer->price,
                'demand_title' => $demand?->title,
            ],
        ];
    }
}
