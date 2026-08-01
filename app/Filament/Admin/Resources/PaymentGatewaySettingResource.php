<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PaymentGatewaySettingResource\Pages;
use App\Models\PaymentGatewaySetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentGatewaySettingResource extends Resource
{
    protected static ?string $model            = PaymentGatewaySetting::class;
    protected static ?string $navigationLabel   = 'Ödeme Sağlayıcıları';
    protected static ?string $navigationIcon    = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup   = 'Ödeme & Abonelik';
    protected static ?string $modelLabel        = 'Ödeme Sağlayıcı';
    protected static ?string $pluralModelLabel  = 'Ödeme Sağlayıcıları';
    protected static ?int    $navigationSort    = 1;

    // Bu tablo sadece migration'da seed edilen 'paytr' / 'iyzico' satırlarını
    // barındırır — admin panelden yeni bir sağlayıcı satırı eklenmesi
    // anlamsız (kod tarafında karşılığı olmaz), bu yüzden Create kapalı.
    public static function canCreate(): bool
    {
        return false;
    }

    /** Bayilik sistemi: ödeme gateway ayarları sadece admin görür. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Sağlayıcı')
                ->schema([
                    Forms\Components\TextInput::make('gateway')
                        ->label('Sağlayıcı Kodu')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->helperText('Kapalıyken bu sağlayıcı üzerinden yeni ödeme başlatılamaz.'),

                    Forms\Components\Toggle::make('is_test_mode')
                        ->label('Test Modu')
                        ->helperText('PayTR/iyzico test ortamına gönderir, gerçek tahsilat yapılmaz. Canlıya almadan önce kapatın.'),
                ])->columns(3),

            Forms\Components\Section::make('Kimlik Bilgileri (API Anahtarları)')
                ->description(
                    'PayTR için: merchant_id, merchant_key, merchant_salt anahtarlarını girin ' .
                    '(Mağaza Paneli → Ayarlar → Bilgilerim). ' .
                    'İsteğe bağlı: merchant_ok_url, merchant_fail_url (ödeme sonrası dönüş adresleri). ' .
                    'Bu alan şifreli olarak saklanır.'
                )
                ->schema([
                    Forms\Components\KeyValue::make('credentials')
                        ->label('Anahtar / Değer')
                        ->keyLabel('Anahtar')
                        ->valueLabel('Değer')
                        ->addActionLabel('Yeni anahtar ekle')
                        ->reorderable(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gateway')
                    ->label('Sağlayıcı')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'paytr'  => 'PayTR',
                        'iyzico' => 'iyzico',
                        default  => ucfirst($state),
                    })
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_test_mode')
                    ->label('Test Modu')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Son Güncelleme')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentGatewaySettings::route('/'),
            'edit'  => Pages\EditPaymentGatewaySetting::route('/{record}/edit'),
        ];
    }
}
