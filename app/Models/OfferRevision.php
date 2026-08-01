<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferRevision extends Model
{
    /** created_at var ama updated_at yok — bu tablo salt-okunur bir log. */
    const UPDATED_AT = null;

    protected $fillable = [
        'offer_id',
        'price',
        'message',
        'portfolio_item_id',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function portfolioItem(): BelongsTo
    {
        return $this->belongsTo(PortfolioItem::class);
    }
}
