<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bir talebe onaylı bir teklif geldiğinde (ModerationService::approveOffer)
 * herkese açık 'demands' kanalında yayınlanır — anasayfadaki/pazaryerindeki
 * canlı liste, ilgili talebin görünen teklif sayısını anlık günceller.
 */
class DemandOfferCountChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $demandId,
        public int $offersCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('demands')];
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->demandId,
            'offers_count' => $this->offersCount,
        ];
    }

    public function broadcastAs(): string
    {
        return 'demand.offer.count.changed';
    }
}
