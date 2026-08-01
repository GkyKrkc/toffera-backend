<?php

namespace App\Filament\Admin\Resources\AgentApplicationResource\Pages;

use App\Filament\Admin\Resources\AgentApplicationResource;
use App\Services\CategoryAccessService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentApplication extends EditRecord
{
    protected static string $resource = AgentApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Admin bu formdan "Uzmanlık Tipi"ni (account_type_group_id) değiştirip
     * kaydederse, kategori bazlı teklif/portföy yetkileri de (bkz.
     * CategoryAccessService) otomatik yeniden hesaplansın — aksi halde
     * grup değişir ama eski kategori izinleri (source='group' satırlar)
     * elde kalır, tutarsızlık oluşur.
     */
    protected function afterSave(): void
    {
        app(CategoryAccessService::class)->syncFromGroup($this->record->fresh());
    }
}
