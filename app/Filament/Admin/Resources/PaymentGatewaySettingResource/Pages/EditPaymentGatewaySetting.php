<?php

namespace App\Filament\Admin\Resources\PaymentGatewaySettingResource\Pages;

use App\Filament\Admin\Resources\PaymentGatewaySettingResource;
use Filament\Resources\Pages\EditRecord;

class EditPaymentGatewaySetting extends EditRecord
{
    protected static string $resource = PaymentGatewaySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
