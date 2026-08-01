<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DemandModerationResource\Pages;
use App\Models\Demand;
use App\Services\ModerationService;
use App\Services\RegionDealerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DemandModerationResource extends Resource
{
    protected static ?string $model            = Demand::class;
    protected static ?string $navigationLabel  = 'Talep Onayları';
    protected static ?string $navigationIcon   = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup  = 'Moderasyon';
    protected static ?string $modelLabel       = 'Talep';
    protected static ?string $pluralModelLabel = 'Talep Onayları';
    protected static ?int    $navigationSort   = 1;

    /**
     * admin: tüm Türkiye. dealer (il/ilçe bayisi): SADECE kendi bölgesine
     * ait talepler — bkz. RegionDealerService::scopeDemandQueryForDealer.
     * Bu, dealer'ın onaylama butonlarını da fiilen sınırlıyor çünkü Filament
     * satır aksiyonları burada dönen kayıtlara bağlı (bölge dışı bir kayıt
     * hiç listede görünmüyor).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        return app(RegionDealerService::class)->scopeDemandQueryForDealer($query, $user);
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
                Tables\Columns\TextColumn::make('title')
                    ->label('Başlık')->limit(30),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Talep Sahibi'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori'),
                Tables\Columns\TextColumn::make('district')
                    ->label('İlçe'),
                Tables\Columns\TextColumn::make('max_budget')
                    ->label('Bütçe')->money('TRY')->sortable(),
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
                    ->visible(fn(Demand $record) => $record->moderation_status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('Onaylandığında talep pazaryerinde yayınlanacak ve eşleşen uzmanlara bildirim gidecek.')
                    ->action(function (Demand $record) {
                        app(ModerationService::class)->approveDemand($record, auth()->user());
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn(Demand $record) => $record->moderation_status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Red sebebi')
                            ->required(),
                    ])
                    ->action(function (Demand $record, array $data) {
                        app(ModerationService::class)->rejectDemand($record, auth()->user(), $data['reason']);
                    }),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDemandModerations::route('/'),
        ];
    }

    public static function canCreate(): bool        { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }
}
