<?php

namespace App\Filament\Admin\Resources\DealerStaffResource\Pages;

use App\Filament\Admin\Resources\DealerStaffResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDealerStaff extends ListRecords
{
    protected static string $resource = DealerStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Personel Ekle'),
        ];
    }
}
