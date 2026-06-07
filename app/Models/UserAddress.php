<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'city',
        'district',
        'neighborhood',
        'full_address',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Okunabilir tam adres stringi
    public function getFormattedAttribute(): string
    {
        return collect([
            $this->neighborhood,
            $this->district,
            $this->city,
        ])->filter()->implode(', ');
    }
}
