<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OfferModerationResource\Pages;
use App\Models\Offer;
use App\Services\ModerationService;
use App\Services\RegionDealerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfferModerationResource extends Resource
{
    protected static ?string $model            = Offer::class;
    protected static ?string $navigationLabel  = 'Teklif Onayları';
    protected static ?string $navigationIcon   = 'heroicon-o-hand-thumb-up';
    protected static ?string $navigationGroup  = 'Moderasyon';
    protected static ?string $modelLabel       = 'Teklif';
    protected static ?string $pluralModelLabel = 'Teklif Onayları';
    protected static ?int    $navigationSort   = 3;

    /** admin: tümü. dealer: sadece kendi bölgesindeki talebe bağlı teklifler — bkz. DemandModerationResource::getEloquentQuery() üzerindeki açıklama, aynı mantık. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        return app(RegionDealerService::class)->scopeOfferQueryForDealer($query, $user);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('moderation_status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('demand.title')
                    ->label('Talep')->limit(30),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Teklif Veren')
                    ->description(fn(Offer $record) => $record->user?->company_name),
                Tables\Columns\TextColumn::make('price')
                    ->label('Fiyat')->money('TRY')->sortable(),
                Tables\Columns\TextColumn::make('message')
                    ->label('Mesaj')->limit(40)
                    ->tooltip(fn(Offer $record) => $record->message),
                Tables\Columns\BadgeColumn::make('moderation_status')
                    ->label('Onay Durumu')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending'  => 'Bekliyor',
                        'approved' => 'Onaylandı',
                        'rejected' => 'Reddedildi',
                        default    => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Gönderildi')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('moderation_status')
                    ->label('Onay Durumu')
                    ->options(['pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi'])
                    ->default('pending'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Onayla')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn(Offer $record) => $record->moderation_status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('Onaylandığında talep sahibine bildirim gidecek ve teklif görünür olacak.')
                    ->action(function (Offer $record) {
                        app(ModerationService::class)->approveOffer($record, auth()->user());
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn(Offer $record) => $record->moderation_status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Red sebebi')
                            ->required(),
                    ])
                    ->action(function (Offer $record, array $data) {
                        app(ModerationService::class)->rejectOffer($record, auth()->user(), $data['reason']);
                    }),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfferModerations::route('/'),
        ];
    }

    public static function canCreate(): bool        { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }
}
