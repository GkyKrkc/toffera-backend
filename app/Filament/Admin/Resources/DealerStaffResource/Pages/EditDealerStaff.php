<?php

namespace App\Filament\Admin\Resources\DealerStaffResource\Pages;

use App\Filament\Admin\Resources\DealerStaffResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDealerStaff extends EditRecord
{
    protected static string $resource = DealerStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Sil'),
        ];
    }
}
