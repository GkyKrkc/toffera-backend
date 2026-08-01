<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'billable_product_id',
        'status',
        'starts_at',
        'ends_at',
        'auto_renew',
        'offers_used_this_period',
        'period_resets_at',
        'payment_id',
    ];

    protected $casts = [
        'starts_at'         => 'datetime',
        'ends_at'           => 'datetime',
        'period_resets_at'  => 'datetime',
        'auto_renew'        => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billableProduct(): BelongsTo
    {
        return $this->belongsTo(BillableProduct::class);
    }
}
