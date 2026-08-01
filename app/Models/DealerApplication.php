<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Bayi olmak istiyorum" başvurusu. Onaylanınca RegionDealerService::
 * approveApplication() gerçek bir RegionDealer kaydı oluşturur — bu model
 * TEK BAŞINA hiçbir yetki taşımaz, sadece başvuru/inceleme sürecini tutar.
 */
class DealerApplication extends Model
{
    protected $fillable = [
        'user_id',
        'il',
        'ilce',
        'motivation',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    /** İlçe belirtilmişse ilçe bayiliği, boşsa il bayiliği başvurusu sayılır. */
    public function requestedRegionType(): string
    {
        return $this->ilce ? 'ilce' : 'il';
    }
}
