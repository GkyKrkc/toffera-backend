<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\PaymentService;
use Filament\Forms\Form;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Tüm ödeme kayıtlarının (Payment) işlem geçmişi/audit listesi —
 * destek/muhasebe için. PayTR kayıtları checkout + callback akışıyla
 * OTOMATİK oluşur/tamamlanır, elle oluşturma/düzenleme bilerek kapalı.
 * Havale/EFT kayıtları da checkout'ta otomatik 'pending' açılır, ama
 * banka tarafından bir callback gelmediği için tamamlanması buradaki
 * Onayla/Reddet aksiyonlarıyla ELLE yapılır (bkz. PaymentService::
 * approveManually()/rejectManually()).
 */
class PaymentResource extends Resource
{
    protected static ?string $model            = Payment::class;
    protected static ?string $navigationLabel   = 'Ödeme İşlemleri';
    protected static ?string $navigationIcon    = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup   = 'Ödeme & Abonelik';
    protected static ?string $modelLabel        = 'Ödeme';
    protected static ?string $pluralModelLabel  = 'Ödeme İşlemleri';
    protected static ?int    $navigationSort    = 4;

    public static function canCreate(): bool        { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }

    /** Bayilik sistemi: ham ödeme kayıtları sadece admin görür (bayi kendi payını DealerRevenueShareResource'tan görür). */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Kullanıcı')
                    ->searchable(),

                Tables\Columns\TextColumn::make('billableProduct.name')
                    ->label('Ürün')
                    ->default('—'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Tutar')
                    ->money('TRY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('gateway')
                    ->label('Sağlayıcı')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'paytr'      => 'PayTR',
                        'iyzico'     => 'iyzico',
                        'havale_eft' => 'Havale/EFT',
                        default      => ucfirst($state),
                    })
                    ->color(fn (string $state) => $state === 'havale_eft' ? 'info' : 'gray'),

                Tables\Columns\TextColumn::make('bankAccount.banka_adi')
                    ->label('Banka Hesabı')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('customer_note')
                    ->label('Kullanıcı Notu')
                    ->default('—')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Durum')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'success',
                        'danger'  => 'failed',
                        'gray'    => 'refunded',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'  => 'Bekliyor',
                        'success'  => 'Başarılı',
                        'failed'   => 'Başarısız',
                        'refunded' => 'İade Edildi',
                        default    => $state,
                    }),

                Tables\Columns\TextColumn::make('gateway_transaction_id')
                    ->label('PayTR Sipariş No')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Ödendi')->dateTime('d.m.Y H:i')->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturuldu')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'pending'  => 'Bekliyor',
                        'success'  => 'Başarılı',
                        'failed'   => 'Başarısız',
                        'refunded' => 'İade Edildi',
                    ]),
                Tables\Filters\SelectFilter::make('gateway')
                    ->label('Sağlayıcı')
                    ->options(['paytr' => 'PayTR', 'iyzico' => 'iyzico', 'havale_eft' => 'Havale/EFT']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Detay'),

                // Sadece bekleyen Havale/EFT ödemelerinde görünür — PayTR
                // zaten callback ile otomatik tamamlanıyor, elle onay/red
                // sadece banka bildirimi olmayan Havale/EFT için gerekli.
                Tables\Actions\Action::make('approve')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Payment $record) => $record->gateway === 'havale_eft' && $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Ödemeyi Onayla')
                    ->modalDescription('Banka hesap ekstresinde bu tutarın gerçekten geldiğini kontrol ettiniz mi? Onaylarsanız kullanıcının aboneliği/kontörü hemen aktif edilir.')
                    ->modalSubmitActionLabel('Evet, Onayla')
                    ->action(function (Payment $record) {
                        app(PaymentService::class)->approveManually($record);

                        Notification::make()
                            ->title('Ödeme onaylandı, hak tanımlandı.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->gateway === 'havale_eft' && $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Ödemeyi Reddet')
                    ->modalDescription('Hesaba para geçmediyse veya tutar uyuşmuyorsa reddedin. Kullanıcıya herhangi bir hak tanımlanmaz.')
                    ->modalSubmitActionLabel('Evet, Reddet')
                    ->action(function (Payment $record) {
                        app(PaymentService::class)->rejectManually($record);

                        Notification::make()
                            ->title('Ödeme reddedildi.')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('id')->label('Ödeme #'),
            TextEntry::make('user.name')->label('Kullanıcı'),
            TextEntry::make('billableProduct.name')->label('Ürün')->default('—'),
            TextEntry::make('amount')->label('Tutar')->money('TRY'),
            TextEntry::make('status')->label('Durum'),
            TextEntry::make('gateway_transaction_id')->label('PayTR Sipariş No')->copyable()->visible(fn (Payment $record) => $record->gateway === 'paytr'),
            TextEntry::make('bankAccount.banka_adi')->label('Gösterilen Banka Hesabı')->default('—')->visible(fn (Payment $record) => $record->gateway === 'havale_eft'),
            TextEntry::make('bankAccount.iban')->label('IBAN')->copyable()->default('—')->visible(fn (Payment $record) => $record->gateway === 'havale_eft'),
            TextEntry::make('customer_note')->label('Kullanıcı Notu')->default('—')->visible(fn (Payment $record) => $record->gateway === 'havale_eft'),
            TextEntry::make('paid_at')->label('Ödendi')->dateTime('d.m.Y H:i'),
            KeyValueEntry::make('raw_response')->label('PayTR Ham Cevabı (callback)')->visible(fn (Payment $record) => $record->gateway === 'paytr'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view'  => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
