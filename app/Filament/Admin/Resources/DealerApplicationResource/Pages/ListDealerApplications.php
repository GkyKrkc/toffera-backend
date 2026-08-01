<?php

namespace App\Filament\Admin\Resources\DealerApplicationResource\Pages;

use App\Filament\Admin\Resources\DealerApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListDealerApplications extends ListRecords
{
    protected static string $resource = DealerApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return []; // başvurular sadece API üzerinden oluşur
    }
}
