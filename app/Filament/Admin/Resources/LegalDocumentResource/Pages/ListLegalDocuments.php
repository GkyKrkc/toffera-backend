<?php

namespace App\Filament\Admin\Resources\LegalDocumentResource\Pages;

use App\Filament\Admin\Resources\LegalDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListLegalDocuments extends ListRecords
{
    protected static string $resource = LegalDocumentResource::class;

    // Bilerek boş — sabit 4 satır var, "Yeni Ekle" butonu yok.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
