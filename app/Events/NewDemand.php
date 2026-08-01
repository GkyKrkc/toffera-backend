<?php

namespace App\Events;

use App\Models\Demand;
use Illuminate\Broadcasting\PrivateChannel;
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
        // ÖNCEDEN: her agentId için AYNI genel 'agents' kanalını tekrar
        // tekrar diziye ekliyordu — N eşleşen agent varsa event N kere
        // aynı genel kanala yayınlanıyordu, o kanalı dinleyen HERKES
        // (eşleşmemiş agent'lar dahil) N adet aynı bildirimi alıyordu.
        //
        // ŞİMDİ: her eşleşen agent'ın GERÇEKTEN kendi özel kanalına
        // (user.{id}) ayrı ayrı yayınlanıyor — sadece o agent duyar,
        // event tek sefer gerçek anlamda "N farklı kanala" gider.
        return array_map(
            fn($id) => new PrivateChannel('user.' . $id),
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
