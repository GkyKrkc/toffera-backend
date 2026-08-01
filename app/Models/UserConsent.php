<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir kullanıcının bir yasal metnin belirli bir versiyonunu ne zaman/hangi
 * IP'den onayladığının kalıcı kaydı. Metin güncellendiğinde (version arttı)
 * yeni bir satır eklenir, eskiler SİLİNMEZ — geçmiş onay kaydı KVKK ispat
 * yükümlülüğü için korunur (bkz. User::pendingConsents()).
 */
class UserConsent extends Model
{
    protected $fillable = [
        'user_id',
        'legal_document_type',
        'version',
        'accepted_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
