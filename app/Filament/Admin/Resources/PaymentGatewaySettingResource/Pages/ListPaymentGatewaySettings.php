<?php

namespace App\Filament\Admin\Resources\PaymentGatewaySettingResource\Pages;

use App\Filament\Admin\Resources\PaymentGatewaySettingResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentGatewaySettings extends ListRecords
{
    protected static string $resource = PaymentGatewaySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
