<?php

namespace App\Events;

use App\Models\Offer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOffer implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Offer $offer) {}

    public function broadcastOn(): array
    {
        // İlan sahibinin private kanalı
        return [
            new PrivateChannel('user.' . $this->offer->demand->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'offer_id'    => $this->offer->id,
            'demand_id'   => $this->offer->demand_id,
            'demand_title'=> $this->offer->demand->title,
            'price'       => $this->offer->price,
            'agent_name'  => $this->offer->user->company_name ?? $this->offer->user->name,
            'message'     => $this->offer->message,
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.offer';
    }
}
