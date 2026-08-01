<?php

namespace App\Filament\Admin\Resources\DealerRevenueShareResource\Pages;

use App\Filament\Admin\Resources\DealerRevenueShareResource;
use Filament\Resources\Pages\ListRecords;

class ListDealerRevenueShares extends ListRecords
{
    protected static string $resource = DealerRevenueShareResource::class;

    protected function getHeaderActions(): array
    {
        return []; // kayıtlar sadece sistem tarafından üretilir, elle ekleme yok
    }
}
