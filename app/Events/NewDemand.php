<?php

namespace App\Events;

use App\Models\Demand;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewDemand implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Demand $demand,
        public array $agentIds = []
    ) {}

    public function broadcastOn(): array
    {
        // Her eşleşen agent'ın kanalına
        return array_map(
            fn($id) => new Channel('agents'),
            $this->agentIds
        );
    }

    public function broadcastWith(): array
    {
        return [
            'demand_id'    => $this->demand->id,
            'title'        => $this->demand->title,
            'category'     => $this->demand->category?->name,
            'district'     => $this->demand->district,
            'min_budget'   => $this->demand->min_budget,
            'max_budget'   => $this->demand->max_budget,
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.demand';
    }
}
