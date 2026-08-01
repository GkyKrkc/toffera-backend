<?php

namespace App\Filament\Admin\Resources\QuickMessageResource\Pages;

use App\Filament\Admin\Resources\QuickMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuickMessage extends EditRecord
{
    protected static string $resource = QuickMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Sil'),
        ];
    }
}
