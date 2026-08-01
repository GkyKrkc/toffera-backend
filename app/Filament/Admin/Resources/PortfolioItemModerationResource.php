<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PortfolioItemModerationResource\Pages;
use App\Models\PortfolioItem;
use App\Services\ModerationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PortfolioItemModerationResource extends Resource
{
    protected static ?string $model            = PortfolioItem::class;
    protected static ?string $navigationLabel  = 'İlan Onayları';
    protected static ?string $navigationIcon   = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup  = 'Moderasyon';
    protected static ?string $modelLabel       = 'İlan';
    protected static ?string $pluralModelLabel = 'İlan Onayları';
    protected static ?int    $navigationSort   = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('moderation_status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    /**
     * Bayilik sistemi kapsamı SADECE talep+teklif onayı (kullanıcının
     * açıkça belirttiği kapsam) — portföy/ilan onayı bunun dışında,
     * admin-only kalıyor. İstenirse ileride dealer'a da açılabilir.
     */
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
                Tables\Columns\TextColumn::make('title')
                    ->label('Başlık')->searchable()->limit(35),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tür')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'vasita'      => 'Araç',
                        'gayrimenkul' => 'Emlak',
                        'elektronik'  => 'Elektronik',
                        default       => $state,
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Sahibi')
                    // NOT: eskiden agent_type doluluğuna bakıyordu — yeni/özel
                    // hesap gruplarında (Plaza, Rent A Car, vb.) agent_type hep
                    // null kaldığı için ticari kullanıcılar yanlışlıkla
                    // "Bireysel" görünüyordu. Gerçek kaynak accountTypeGroup.kind.
                    ->description(fn(PortfolioItem $record) => $record->user?->accountTypeGroup?->kind === 'commercial' ? 'Kurumsal' : 'Bireysel'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Fiyat')->money('TRY')->sortable(),
                Tables\Columns\IconColumn::make('ownership_verified_at')
                    ->label('Sahiplik')
                    ->boolean()
                    ->getStateUsing(fn(PortfolioItem $record) => $record->ownership_verified_at !== null)
                    ->trueColor('success')->falseColor('warning')
                    ->tooltip(fn(PortfolioItem $record) => $record->ownership_verified_at ? 'Doğrulandı' : 'Belge onaylanmamış'),
                Tables\Columns\BadgeColumn::make('moderation_status')
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
                    ->label('Oluşturuldu')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('moderation_status')
                    ->label('Durum')
                    ->options(['pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi'])
                    ->default('pending'),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tür')
                    ->options(['vasita' => 'Araç', 'gayrimenkul' => 'Emlak', 'elektronik' => 'Elektronik']),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Görüntüle')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (PortfolioItem $record) => $record->title)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat')
                    ->modalWidth('3xl')
                    ->modalContent(function (PortfolioItem $record) {
                        // DİKKAT: portfolio_items tablosunda "images" adında
                        // düz bir DB kolonu var, bu $record->images (magic
                        // property) ile images() İLİŞKİSİNİ gölgeliyor —
                        // ->load('images') sonrası bile $record->images hep
                        // o ham kolonu (null) döner. Bu yüzden ilişkiyi
                        // METOT olarak (images()) çağırıp ayrı değişken
                        // olarak geçiriyoruz, magic property'ye hiç dokunmuyoruz.
                        return view('filament.admin.portfolio-item-preview', [
                            'item'          => $record->load('documents', 'user', 'category'),
                            'itemImages'    => $record->images()->orderBy('sort_order')->get(),
                        ]);
                    }),
                Tables\Actions\Action::make('approve')
                    ->label('Onayla')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn(PortfolioItem $record) => $record->moderation_status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (PortfolioItem $record) {
                        app(ModerationService::class)->approveListing($record, auth()->user());
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn(PortfolioItem $record) => $record->moderation_status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Red sebebi')
                            ->required(),
                    ])
                    ->action(function (PortfolioItem $record, array $data) {
                        app(ModerationService::class)->rejectListing($record, auth()->user(), $data['reason']);
                    }),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPortfolioItemModerations::route('/'),
        ];
    }

    public static function canCreate(): bool        { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }
}
