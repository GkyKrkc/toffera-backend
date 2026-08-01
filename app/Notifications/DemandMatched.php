<?php

namespace App\Notifications;

use App\Models\Demand;

/**
 * Yeni bir talep, acentenin portföyüyle eşleştiğinde ACENTEYE gönderilir.
 * Tetiklenme yeri: PortfolioMatcher — eşik (40) üstü skorla eşleşen
 * her acente için (mevcut NewDemand broadcast akışının yanına).
 */
class DemandMatched extends BaseDemandNotification
{
    public function __construct(
        public Demand $demand,
        public ?int $score = null,
    ) {}

    public function key(): string
    {
        return 'demand.matched';
    }

    protected function payload($notifiable): array
    {
        $budget = $this->demand->max_budget
            ? number_format((float) $this->demand->max_budget, 0, ',', '.') . ' ₺'
            : null;

        return [
            'title'   => 'Portföyünüze uygun yeni talep',
            'message' => sprintf(
                'Kriterlerinize uyan yeni bir talep var: "%s"%s',
                $this->demand->title ?? 'Yeni talep',
                $budget ? ' — Bütçe: ' . $budget : ''
            ),
            'url'  => '/market/' . $this->demand->id,
            'icon' => 'target',
            'meta' => [
                'demand_id' => $this->demand->id,
                'score'     => $this->score,
                'demand_title' => $this->demand->title,
            ],
        ];
    }
}
