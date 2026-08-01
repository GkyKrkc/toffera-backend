<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Yasal metin (Kullanıcı Sözleşmesi, KVKK Aydınlatma Metni, Açık Rıza
 * Metni, Ticari Elektronik İleti Onayı). Admin panelden düzenlenir —
 * body değişince version otomatik artar (bkz.
 * LegalDocumentResource\Pages\EditLegalDocument::handleRecordUpdate()).
 * Frontend'e her zaman renderedBody() (merge tag'leri çözülmüş hali)
 * gönderilir, ham {placeholder}'lı body asla dışarı çıkmaz.
 */
class LegalDocument extends Model
{
    public const TYPE_USER_AGREEMENT   = 'user_agreement';
    public const TYPE_KVKK_DISCLOSURE  = 'kvkk_disclosure';
    public const TYPE_EXPLICIT_CONSENT = 'explicit_consent';
    public const TYPE_COMMERCIAL_MSG   = 'commercial_electronic_message';

    protected $fillable = [
        'type',
        'title',
        'body',
        'version',
        'is_mandatory',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * body içindeki {sirket_unvani} vb. merge tag'lerini CompanySetting'ten
     * gelen gerçek değerlerle değiştirir. Ayrıca {bugun} (bugünün tarihi)
     * ve {kullanici_adi}/{kullanici_email} (varsa, kişiselleştirme için)
     * destekler.
     */
    public function renderedBody(?User $user = null): string
    {
        $tags = CompanySetting::current()->mergeTags();
        $tags['bugun'] = now()->translatedFormat('d.m.Y');
        $tags['kullanici_adi'] = $user?->name ?: '';
        $tags['kullanici_email'] = $user?->email ?: '';

        $body = $this->body;
        foreach ($tags as $key => $value) {
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }

        return $body;
    }
}
