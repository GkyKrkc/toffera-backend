<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PortfolioDocumentResource\Pages;
use App\Models\PortfolioDocument;
use App\Models\User;
use App\Services\ModerationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PortfolioDocumentResource extends Resource
{
    protected static ?string $model            = PortfolioDocument::class;
    protected static ?string $navigationLabel  = 'Belge Onayları';
    protected static ?string $navigationIcon   = 'heroicon-o-document-check';
    protected static ?string $navigationGroup  = 'Moderasyon';
    protected static ?string $modelLabel       = 'Belge';
    protected static ?string $pluralModelLabel = 'Belge Onayları';
    protected static ?int    $navigationSort   = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    /** Bayilik sistemi: belge onayları SADECE genel merkezde (admin) kalır — kullanıcı kararı. */
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
                Tables\Columns\TextColumn::make('file_name')
                    ->label('Dosya')->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Etiket')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'sahiplik_belgesi' => 'Sahiplik Belgesi',
                        default             => $state ?? '—',
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('portfolioItem.title')
                    ->label('İlan')->limit(30),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Yükleyen'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Durum')
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
                    ->label('Yüklendi')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Durum')
                    ->options(['pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi'])
                    ->default('pending'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Görüntüle')
                    ->icon('heroicon-o-eye')
                    ->url(fn(PortfolioDocument $record) => $record->url)
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('approve')
                    ->label('Onayla')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn(PortfolioDocument $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (PortfolioDocument $record) {
                        app(ModerationService::class)->approveDocument($record, auth()->user());
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn(PortfolioDocument $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Red sebebi')
                            ->required(),
                    ])
                    ->action(function (PortfolioDocument $record, array $data) {
                        app(ModerationService::class)->rejectDocument($record, auth()->user(), $data['reason']);
                    }),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPortfolioDocuments::route('/'),
        ];
    }

    public static function canCreate(): bool        { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }
}
