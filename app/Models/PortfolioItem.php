<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioItem extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'description',
        'price', 'status', 'features', 'images',
        'notified_demand_ids', 'district',
    ];

    protected function casts(): array
    {
        return [
            'features'            => 'array',
            'images'              => 'array',
            'notified_demand_ids' => 'array',
            'price'               => 'decimal:2',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function isNotifiedFor(int $demandId): bool
    {
        return in_array($demandId, $this->notified_demand_ids ?? []);
    }

    public function markNotifiedFor(int $demandId): void
    {
        $ids   = $this->notified_demand_ids ?? [];
        $ids[] = $demandId;
        $this->update(['notified_demand_ids' => array_unique($ids)]);
    }

}
