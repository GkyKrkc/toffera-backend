<?php

namespace App\Filament\Admin\Resources\QuickMessageResource\Pages;

use App\Filament\Admin\Resources\QuickMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQuickMessages extends ListRecords
{
    protected static string $resource = QuickMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Yeni Hazır Mesaj'),
        ];
    }
}
