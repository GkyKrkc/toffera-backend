<?php

namespace App\Filament\Admin\Resources\AgentApplicationResource\Pages;

use App\Filament\Admin\Resources\AgentApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentApplication extends EditRecord
{
    protected static string $resource = AgentApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
