<?php

namespace App\Notifications;

use App\Models\Offer;

/**
 * Bir acente, talebe verdiği teklifi (fiyat/mesaj/portföy) güncellediğinde
 * talep sahibine gönderilir. Tetiklenme yeri: OfferController@update —
 * sadece teklif zaten talep sahibine görünür durumdaysa (moderation_status
 * = approved) gönderilir; henüz admin onayı bekleyen bir teklif talep
 * sahibine hiç gösterilmediği için erken bildirim kafa karıştırır.
 */
class OfferUpdated extends BaseDemandNotification
{
    public function __construct(public Offer $offer) {}

    public function key(): string
    {
        return 'offer.updated';
    }

    protected function payload($notifiable): array
    {
        $demand = $this->offer->demand;

        return [
            'title'   => 'Bir teklif güncellendi',
            'message' => sprintf(
                '"%s" talebinize verilen teklif %s ₺ olarak güncellendi.',
                $demand?->title ?? 'Talebiniz',
                number_format((float) $this->offer->price, 0, ',', '.')
            ),
            'url'  => '/market/' . $this->offer->demand_id . '/offers/' . $this->offer->id,
            'icon' => 'refresh-cw',
            'meta' => [
                'demand_id' => $this->offer->demand_id,
                'offer_id'  => $this->offer->id,
                'price'     => $this->offer->price,
                'demand_title' => $demand?->title,
            ],
        ];
    }
}
