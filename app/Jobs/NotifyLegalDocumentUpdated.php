<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\LegalDocument;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Zorunlu bir yasal metin (Kullanıcı Sözleşmesi / KVKK Aydınlatma Metni)
 * admin panelden güncellenip versiyonu artınca dispatch edilir (bkz.
 * LegalDocumentResource\Pages\EditLegalDocument::handleRecordUpdate()).
 * Telefon doğrulanmış TÜM kullanıcılara bildirim gönderir — engelleyici
 * onay ekranı (LegalReconsentGate.jsx) zaten bir sonraki girişte
 * otomatik açılır, bu job sadece "hemen haberdar olsunlar" için proaktif
 * bir bildirim/e-posta ekler.
 *
 * chunkById(200) — kullanıcı sayısı ne olursa olsun RAM'i şişirmeden,
 * parça parça işler (bkz. CloseOffersForSoldPortfolioItem ile aynı desen).
 */
class NotifyLegalDocumentUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];
    public int $timeout = 600;

    public function __construct(protected int $legalDocumentId)
    {
    }

    public function handle(): void
    {
        $doc = LegalDocument::find($this->legalDocumentId);
        if (!$doc) {
            return;
        }

        User::query()
            ->whereNotNull('phone_verified_at')
            ->chunkById(200, function ($users) use ($doc) {
                foreach ($users as $user) {
                    // Zaten güncel versiyonu onaylamışsa (ör. kayıt bu
                    // versiyonla YENİ yapıldıysa, ya da retry sırasında
                    // tekrar onaylamışsa) tekrar bildirim atma.
                    if ($user->latestConsentVersion($doc->type) >= $doc->version) {
                        continue;
                    }

                    $user->notify(new AppNotification(NotificationType::LEGAL_DOCUMENT_UPDATED, [
                        'document_title' => $doc->title,
                        'document_type'  => $doc->type,
                        'action_url'     => '/settings?tab=legal',
                    ]));
                }
            });
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('NotifyLegalDocumentUpdated başarısız oldu', [
            'legal_document_id' => $this->legalDocumentId,
            'error'             => $exception->getMessage(),
        ]);
    }
}
