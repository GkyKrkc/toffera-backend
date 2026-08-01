<?php

namespace App\Filament\Admin\Resources\RegionDealerResource\Pages;

use App\Filament\Admin\Resources\RegionDealerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRegionDealer extends EditRecord
{
    protected static string $resource = RegionDealerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Sil'),
        ];
    }
}
