<?php

namespace App\Filament\Admin\Resources\BillableProductResource\Pages;

use App\Filament\Admin\Resources\BillableProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBillableProducts extends ListRecords
{
    protected static string $resource = BillableProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Yeni Ürün'),
        ];
    }
}
