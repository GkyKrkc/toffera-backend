<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AgentDocument extends Model
{
    protected $fillable = [
        'user_id',
        'document_type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    // Belge türleri ve Türkçe karşılıkları
    public const TYPE_LABELS = [
        'isyeri_belgesi'  => 'İşyeri Belgesi',
        'ticaret_sicili'  => 'Ticaret Sicil Kaydı',
        'esnaf_oda_kaydi' => 'Esnaf / Oda Kaydı',
        'vergi_levhasi'   => 'Vergi Levhası',
    ];

    // Zorunlu belgeler
    public const REQUIRED_TYPES = [
        'ticaret_sicili',
        'vergi_levhasi',
    ];

    // ── İlişkiler ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Accessor'lar ──────────────────────────────────────────

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->document_type] ?? $this->document_type;
    }

    public function getFileSizeHumanAttribute(): string
    {
        if (!$this->file_size) return 'Bilinmiyor';
        $kb = $this->file_size / 1024;
        return $kb > 1024
            ? round($kb / 1024, 1) . ' MB'
            : round($kb, 0) . ' KB';
    }

    // ── Yardımcılar ───────────────────────────────────────────

    // Private disk için geçici imzalı URL (30 dakika)
    public function getTemporaryUrl(int $minutes = 30): string
    {
        return Storage::disk('private')->temporaryUrl(
            $this->file_path,
            now()->addMinutes($minutes)
        );
    }

    public function fileExists(): bool
    {
        return Storage::disk('private')->exists($this->file_path);
    }
}