<?php

namespace App\Events;

use App\Models\Demand;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bir talep moderasyondan geçip yayına alındığında (approveDemand)
 * herkese açık 'demands' kanalında yayınlanır — anasayfadaki canlı talep
 * listesi bunu dinleyip yeni talebi en üste, animasyonla ekler. NewDemand
 * event'inden farkı: bu private/agent-eşleşmeli değil, herkes duyar.
 */
class DemandPublished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Demand $demand) {}

    public function broadcastOn(): array
    {
        return [new Channel('demands')];
    }

    public function broadcastWith(): array
    {
        $this->demand->loadMissing('category:id,name,slug,icon');

        return [
            'id'         => $this->demand->id,
            'title'      => $this->demand->title,
            'district'   => $this->demand->district,
            'features'   => $this->demand->features,
            'max_budget' => $this->demand->max_budget,
            'expires_at' => $this->demand->expires_at?->toIso8601String(),
            'category'   => $this->demand->category ? [
                'id'   => $this->demand->category->id,
                'name' => $this->demand->category->name,
                'slug' => $this->demand->category->slug,
                'icon' => $this->demand->category->icon,
            ] : null,
            'offers_count' => 0,
        ];
    }

    public function broadcastAs(): string
    {
        return 'demand.published';
    }
}
