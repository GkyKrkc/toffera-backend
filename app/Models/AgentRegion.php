<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRegion extends Model
{
    protected $fillable = [
        'user_id',
        'city',
        'district',
        'neighborhood',
        'category_slug',
        'notify_new_demand',
    ];

    protected function casts(): array
    {
        return [
            'notify_new_demand' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Bölge etiketini döner: "Onikişubat, Kahramanmaraş"
    public function getFormattedAttribute(): string
    {
        return collect([
            $this->neighborhood,
            $this->district,
            $this->city,
        ])->filter()->implode(', ');
    }
}
