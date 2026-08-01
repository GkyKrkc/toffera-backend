<?php

namespace App\Filament\Admin\Resources\RegionDealerResource\Pages;

use App\Filament\Admin\Resources\RegionDealerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegionDealers extends ListRecords
{
    protected static string $resource = RegionDealerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Yeni Bayilik Ata'),
        ];
    }
}
