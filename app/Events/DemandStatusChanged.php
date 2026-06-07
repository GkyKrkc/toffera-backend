<?php

namespace App\Events;

use App\Models\Demand;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DemandStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Demand $demand) {}

    public function broadcastOn(): array
    {
        return [new Channel('demands')];
    }

    public function broadcastWith(): array
    {
        return [
            'id'     => $this->demand->id,
            'status' => $this->demand->status,
        ];
    }

    public function broadcastAs(): string
    {
        return 'demand.status.changed';
    }
}
