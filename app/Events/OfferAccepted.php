<?php

namespace App\Events;

use App\Models\Offer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OfferAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Offer $offer) {}

    public function broadcastOn(): array
    {
        // Teklif sahibi agent'ın kanalı
        return [
            new PrivateChannel('user.' . $this->offer->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'offer_id'     => $this->offer->id,
            'demand_id'    => $this->offer->demand_id,
            'demand_title' => $this->offer->demand->title,
            'price'        => $this->offer->price,
            'owner_name'   => $this->offer->demand->user->name,
            'owner_phone'  => $this->offer->demand->user->phone,
        ];
    }

    public function broadcastAs(): string
    {
        return 'offer.accepted';
    }
}
