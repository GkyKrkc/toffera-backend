<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DealerRevenueShareResource\Pages;
use App\Models\DealerRevenueShare;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bayilik gelir payı raporu — admin TÜMÜNÜ görür ve "ödendi" işaretleyebilir
 * (gerçek para transferi bu sistemin DIŞINDA, elle yapılıyor — bkz.
 * RegionDealerService::recordRevenueShareForPayment). Dealer (il bayisi)
 * SADECE kendi payını, salt okunur olarak görür.
 */
class DealerRevenueShareResource extends Resource
{
    protected static ?string $model            = DealerRevenueShare::class;
    protected static ?string $navigationLabel  = 'Bayilik Gelir Payları';
    protected static ?string $navigationIcon   = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup  = 'Bayilik Sistemi';
    protected static ?string $modelLabel       = 'Gelir Payı';
    protected static ?string $pluralModelLabel = 'Gelir Payları';
    protected static ?int    $navigationSort   = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && ($user->hasRole('admin') || $user->hasRole('dealer'));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        // Dealer: sadece kendi bayilik ataması/atamalarına ait pay kayıtları.
        $dealerIds = $user?->regionDealerAssignments()->pluck('id') ?? collect();

        return $query->whereIn('region_dealer_id', $dealerIds);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]); // sadece tablo + özel aksiyon, form yok
    }

    public static function table(Table $table): Table
    {
        $isAdmin = auth()->user()?->hasRole('admin') ?? false;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('regionDealer.il')
                    ->label('İl')
                    ->description(fn (DealerRevenueShare $r) => $r->regionDealer?->user?->name)
                    ->visible($isAdmin),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Uzman')
                    ->description(fn (DealerRevenueShare $r) => $r->user?->city),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Ödeme Tutarı')
                    ->money('TRY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('share_percent')
                    ->label('Oran')
                    ->formatStateUsing(fn ($state) => "%{$state}"),

                Tables\Columns\TextColumn::make('share_amount')
                    ->label('Bayi Payı')
                    ->money('TRY')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Durum')
                    ->colors(['warning' => 'pending', 'success' => 'paid'])
                    ->formatStateUsing(fn ($state) => $state === 'paid' ? 'Ödendi' : 'Bekliyor'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Ödeme Tarihi')
                    ->dateTime('d.m.Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Durum')
                    ->options(['pending' => 'Bekliyor', 'paid' => 'Ödendi']),
            ])
            ->actions($isAdmin ? [
                Tables\Actions\Action::make('markPaid')
                    ->label('Ödendi İşaretle')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DealerRevenueShare $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('paid_note')
                            ->label('Not (ör. dekont no)')
                            ->maxLength(255),
                    ])
                    ->action(function (DealerRevenueShare $record, array $data) {
                        $record->update([
                            'status'    => 'paid',
                            'paid_at'   => now(),
                            'paid_note' => $data['paid_note'] ?? null,
                        ]);
                    }),
            ] : []);
    }

    public static function canCreate(): bool        { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDealerRevenueShares::route('/'),
        ];
    }
}
