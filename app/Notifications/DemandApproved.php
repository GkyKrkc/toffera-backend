<?php

namespace App\Notifications;

use App\Models\Demand;

/**
 * Talep moderasyon kontrolünden geçip yayına alındığında TALEP SAHİBİNE gönderilir.
 * Tetiklenme yeri: talebin status'ü 'active' yapıldığı an
 * (admin onayı / Filament aksiyonu veya otomatik onay akışı).
 */
class DemandApproved extends BaseDemandNotification
{
    public function __construct(public Demand $demand) {}

    public function key(): string
    {
        return 'demand.approved';
    }

    protected function payload($notifiable): array
    {
        return [
            'title'   => 'Talebiniz yayına alındı',
            'message' => sprintf(
                '"%s" talebiniz başarıyla yayınlandı. Kriterlerinize uyan uzmanlar teklif vermeye başlayabilir.',
                $this->demand->title ?? 'Talebiniz'
            ),
            'url'  => '/market/' . $this->demand->id,
            'icon' => 'shield-check',
            'meta' => [
                'demand_id' => $this->demand->id,
                'demand_title' => $this->demand->title,
            ],
        ];
    }
}
