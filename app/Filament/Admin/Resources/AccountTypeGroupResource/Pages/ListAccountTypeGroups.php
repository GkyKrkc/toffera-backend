<?php

namespace App\Filament\Admin\Resources\AccountTypeGroupResource\Pages;

use App\Filament\Admin\Resources\AccountTypeGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountTypeGroups extends ListRecords
{
    protected static string $resource = AccountTypeGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Yeni Grup'),
        ];
    }
}
