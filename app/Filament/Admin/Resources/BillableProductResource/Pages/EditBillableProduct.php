<?php

namespace App\Filament\Admin\Resources\BillableProductResource\Pages;

use App\Filament\Admin\Resources\BillableProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBillableProduct extends EditRecord
{
    protected static string $resource = BillableProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Sil'),
        ];
    }
}
