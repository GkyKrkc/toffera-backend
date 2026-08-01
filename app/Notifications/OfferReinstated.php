<?php

namespace App\Notifications;

use App\Models\Offer;

/**
 * Bir başka acentenin kabul edilmiş teklifinden vazgeçmesi sonucu, bu
 * kabul yüzünden otomatik reddedilmiş kardeş teklifiniz eski durumuna
 * (pending/reviewing) geri döndüğünde gönderilir. Tetiklenme yeri:
 * OfferController@withdraw.
 */
class OfferReinstated extends BaseDemandNotification
{
    public function __construct(public Offer $offer) {}

    public function key(): string
    {
        return 'offer.reinstated';
    }

    protected function payload($notifiable): array
    {
        $demand = $this->offer->demand;

        return [
            'title'   => 'Teklifiniz tekrar değerlendirmeye açıldı',
            'message' => sprintf(
                '"%s" talebi için kabul edilen başka bir teklif geri çekildiği için, sizin teklifiniz tekrar değerlendirmeye açıldı.',
                $demand?->title ?? 'Talep'
            ),
            'url'  => '/market/' . $this->offer->demand_id . '/offers/' . $this->offer->id,
            'icon' => 'refresh-ccw',
            'meta' => [
                'demand_id' => $this->offer->demand_id,
                'offer_id'  => $this->offer->id,
                'demand_title' => $demand?->title,
            ],
        ];
    }
}
