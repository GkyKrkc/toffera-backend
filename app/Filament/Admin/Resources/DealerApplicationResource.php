<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DealerApplicationResource\Pages;
use App\Models\DealerApplication;
use App\Services\RegionDealerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * "Bayi olmak istiyorum" başvurularının incelendiği ekran — SADECE admin.
 * Onaylanınca RegionDealerService::approveApplication() gerçek RegionDealer
 * kaydını oluşturur (bkz. RegionDealerResource'daki "tek il bayisi" kuralı
 * burada da aynen uygulanıyor, çakışma varsa onay reddedilir).
 */
class DealerApplicationResource extends Resource
{
    protected static ?string $model            = DealerApplication::class;
    protected static ?string $navigationLabel  = 'Bayilik Başvuruları';
    protected static ?string $navigationIcon   = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationGroup  = 'Bayilik Sistemi';
    protected static ?string $modelLabel       = 'Başvuru';
    protected static ?string $pluralModelLabel = 'Bayilik Başvuruları';
    protected static ?int    $navigationSort   = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canCreate(): bool { return false; } // başvuru sadece API üzerinden

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Başvuru')
                ->schema([
                    Forms\Components\TextInput::make('user.name')
                        ->label('Başvuran')->disabled(),
                    Forms\Components\TextInput::make('user.phone')
                        ->label('Telefon')->disabled(),
                    Forms\Components\TextInput::make('il')
                        ->label('İl')->disabled(),
                    Forms\Components\TextInput::make('ilce')
                        ->label('İlçe')->disabled()->placeholder('— (il bayiliği talebi)'),
                    Forms\Components\Textarea::make('motivation')
                        ->label('Başvuru Açıklaması')
                        ->disabled()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('status')
                        ->label('Durum')
                        ->options([
                            'pending'  => 'Beklemede',
                            'approved' => 'Onaylandı',
                            'rejected' => 'Reddedildi',
                        ])->disabled(),
                    Forms\Components\Textarea::make('admin_note')
                        ->label('Admin Notu / Red Sebebi')
                        ->disabled()
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Başvuran')
                    ->description(fn (DealerApplication $r) => $r->user?->phone)
                    ->searchable(),

                Tables\Columns\TextColumn::make('il')
                    ->label('İl')
                    ->searchable(),

                Tables\Columns\TextColumn::make('ilce')
                    ->label('İlçe')
                    ->placeholder('— (il bayiliği)'),

                Tables\Columns\TextColumn::make('motivation')
                    ->label('Açıklama')
                    ->limit(50)
                    ->tooltip(fn (DealerApplication $r) => $r->motivation),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Durum')
                    ->colors(['warning' => 'pending', 'success' => 'approved', 'danger' => 'rejected'])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'  => 'Beklemede',
                        'approved' => 'Onaylandı',
                        'rejected' => 'Reddedildi',
                        default    => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Başvuru Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Durum')
                    ->options(['pending' => 'Beklemede', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi'])
                    ->default('pending'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DealerApplication $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('Onaylandığında bu kullanıcıya seçtiği il/ilçe için bayilik ataması otomatik oluşturulacak ve "dealer" rolü verilecek.')
                    ->action(function (DealerApplication $record) {
                        try {
                            app(RegionDealerService::class)->approveApplication($record, auth()->user());
                            Notification::make()->title('Başvuru onaylandı, bayilik ataması oluşturuldu.')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DealerApplication $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Red Sebebi')
                            ->required(),
                    ])
                    ->action(function (DealerApplication $record, array $data) {
                        app(RegionDealerService::class)->rejectApplication($record, auth()->user(), $data['reason']);
                        Notification::make()->title('Başvuru reddedildi.')->warning()->send();
                    }),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDealerApplications::route('/'),
        ];
    }
}
