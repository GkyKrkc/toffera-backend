<?php

namespace App\Filament\Admin\Resources\LegalDocumentResource\Pages;

use App\Filament\Admin\Resources\LegalDocumentResource;
use App\Jobs\NotifyLegalDocumentUpdated;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLegalDocument extends EditRecord
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return []; // Silme yok — sabit 4 tip, bkz. LegalDocumentResource::canDelete().
    }

    /**
     * Metin (body) gerçekten değiştiyse versiyonu otomatik artırır ve
     * published_at'ı günceller. Bu, zorunlu metinlerde mevcut
     * kullanıcıların bir sonraki girişte yeniden onay istemesini
     * tetikler (bkz. User::pendingConsents(), LegalReconsentGate.jsx).
     * Sadece başlık gibi kozmetik bir alan değiştiyse versiyon ARTMAZ.
     *
     * Zorunlu (is_mandatory) bir metin gerçekten güncellendiyse, ayrıca
     * TÜM kullanıcılara proaktif bir bildirim/e-posta gönderilir (bkz.
     * NotifyLegalDocumentUpdated) — kullanıcı bir sonraki girişi
     * beklemeden haberdar olsun diye.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $bodyChanged = ($data['body'] ?? $record->body) !== $record->body;

        if ($bodyChanged) {
            $data['version']      = $record->version + 1;
            $data['published_at'] = now();
        }

        $record->update($data);

        if ($bodyChanged && $record->is_mandatory) {
            NotifyLegalDocumentUpdated::dispatch($record->id);

            Notification::make()
                ->title('Versiyon ' . $record->version . ' yayınlandı')
                ->body('Bu metin zorunlu olduğu için, mevcut kullanıcılara bildirim gönderiliyor ve bir sonraki girişlerinde yeniden onay istenecek.')
                ->warning()
                ->send();
        }

        return $record;
    }
}
