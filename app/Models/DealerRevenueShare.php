<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir ödemeden (abonelik/kontör) il bayisine ayrılan pay kaydı. Ödeme bu
 * aşamada otomatik yapılmıyor — admin panelinden elle ödenip status='paid'
 * olarak işaretleniyor (bkz. RegionDealerService / PaymentService hook'u).
 */
class DealerRevenueShare extends Model
{
    protected $fillable = [
        'region_dealer_id',
        'payment_id',
        'user_id',
        'amount',
        'share_percent',
        'share_amount',
        'status',
        'paid_at',
        'paid_note',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'share_percent' => 'decimal:2',
            'share_amount'  => 'decimal:2',
            'paid_at'       => 'datetime',
        ];
    }

    public function regionDealer(): BelongsTo
    {
        return $this->belongsTo(RegionDealer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** Payı hak eden uzman (ödemeyi yapan kullanıcı). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
