<?php

namespace App\Notifications;

use App\Models\Offer;

/**
 * Talep sahibi, kabul ettiği teklif için gerçek satışın tamamlandığını
 * onayladığında ("Satışı Onayla") teklifi veren acenteye gönderilir.
 * Tetiklenme yeri: OfferController@confirmSale. Bu noktadan sonra
 * teklif kesinleşmiş sayılır, acente artık vazgeçemez.
 */
class SaleConfirmed extends BaseDemandNotification
{
    public function __construct(public Offer $offer) {}

    public function key(): string
    {
        return 'offer.sale_confirmed';
    }

    protected function payload($notifiable): array
    {
        $demand = $this->offer->demand;

        return [
            'title'   => 'Satışınız onaylandı',
            'message' => sprintf(
                '"%s" talebi için verdiğiniz %s ₺ tutarındaki teklif, talep sahibi tarafından satış olarak onaylandı. Tebrikler!',
                $demand?->title ?? 'Talep',
                number_format((float) $this->offer->price, 0, ',', '.')
            ),
            'url'  => '/market/' . $this->offer->demand_id . '/offers/' . $this->offer->id,
            'icon' => 'badge-check',
            'meta' => [
                'demand_id' => $this->offer->demand_id,
                'offer_id'  => $this->offer->id,
                'price'     => $this->offer->price,
                'demand_title' => $demand?->title,
            ],
        ];
    }
}
