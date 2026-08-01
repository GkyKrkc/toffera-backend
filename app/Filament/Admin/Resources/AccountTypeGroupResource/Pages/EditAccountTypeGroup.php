<?php

namespace App\Filament\Admin\Resources\AccountTypeGroupResource\Pages;

use App\Filament\Admin\Resources\AccountTypeGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccountTypeGroup extends EditRecord
{
    protected static string $resource = AccountTypeGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Sil'),
        ];
    }
}
