<?php

namespace App\Filament\Admin\Resources\DemandModerationResource\Pages;

use App\Filament\Admin\Resources\DemandModerationResource;
use Filament\Resources\Pages\ListRecords;

class ListDemandModerations extends ListRecords
{
    protected static string $resource = DemandModerationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
