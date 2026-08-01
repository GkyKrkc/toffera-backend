<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RegionDealerResource\Pages;
use App\Models\RegionDealer;
use App\Models\User;
use App\Support\TurkiyeIlleri;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * İl/ilçe bayilik atamaları — SADECE admin (genel merkez) erişebilir.
 * Bayi kullanıcılar burayı görmez (bkz. canViewAny) — kendi bölgesindeki
 * moderasyonu DemandModerationResource/OfferModerationResource üzerinden,
 * gelir payını ise DealerRevenueShareResource üzerinden görür.
 */
class RegionDealerResource extends Resource
{
    protected static ?string $model            = RegionDealer::class;
    protected static ?string $navigationLabel  = 'İl/İlçe Bayilikleri';
    protected static ?string $navigationIcon   = 'heroicon-o-map';
    protected static ?string $navigationGroup  = 'Bayilik Sistemi';
    protected static ?string $modelLabel       = 'Bayilik';
    protected static ?string $pluralModelLabel = 'Bayilikler';
    protected static ?int    $navigationSort   = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Bayilik Ataması')
                ->description('İl bayisi kendi ilinden başvuran uzmanların abonelik/kontör gelirinden pay alır (aşağıdaki yüzde). İlçe bayisi ise SADECE o ilçedeki talep/teklif onay yetkisini il bayisinden devralır — ayrı bir gelir payı yoktur.')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Bayi (Kullanıcı)')
                        ->relationship('user', 'name')
                        ->getOptionLabelFromRecordUsing(fn (User $u) => "{$u->name} — {$u->phone}" . ($u->company_name ? " ({$u->company_name})" : ''))
                        ->searchable(['name', 'phone', 'company_name'])
                        ->preload()
                        ->required()
                        ->helperText('Bu kullanıcıya kaydettikten sonra otomatik olarak "dealer" rolü verilir, admin panele girebilir hale gelir.'),

                    Forms\Components\Select::make('region_type')
                        ->label('Bayilik Türü')
                        ->options([
                            'il'   => 'İl Bayiliği',
                            'ilce' => 'İlçe Bayiliği',
                        ])
                        ->required()
                        ->live()
                        ->native(false),

                    Forms\Components\Select::make('il')
                        ->label('İl')
                        ->options(TurkiyeIlleri::options())
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->rule(function (Get $get, ?Model $record) {
                            return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                if ($get('region_type') !== 'il') {
                                    return;
                                }
                                $exists = RegionDealer::query()
                                    ->where('region_type', 'il')
                                    ->where('il', $value)
                                    ->where('is_active', true)
                                    ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                    ->exists();
                                if ($exists) {
                                    $fail('Bu ile zaten aktif bir il bayisi atanmış. Önce mevcut atamayı pasifleştirin.');
                                }
                            };
                        }),

                    Forms\Components\TextInput::make('ilce')
                        ->label('İlçe')
                        ->required(fn (Get $get) => $get('region_type') === 'ilce')
                        ->visible(fn (Get $get) => $get('region_type') === 'ilce')
                        ->helperText('Bu ildeki mevcut bir talebin lokasyon bilgisindeki ilçe yazımıyla BİREBİR aynı olmalı (büyük/küçük harf ve Türkçe karakterler dahil) — aksi halde eşleştirme çalışmaz.'),

                    Forms\Components\TextInput::make('revenue_share_percent')
                        ->label('Gelir Payı (%)')
                        ->numeric()
                        ->default(30)
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->visible(fn (Get $get) => $get('region_type') === 'il')
                        ->helperText('Sadece il bayiliğinde geçerli — ilçe bayisinin ayrı bir geliri yok.'),

                    Forms\Components\Toggle::make('can_approve_demands')
                        ->label('Talep Onayı Yetkisi')
                        ->default(true),

                    Forms\Components\Toggle::make('can_approve_offers')
                        ->label('Teklif Onayı Yetkisi')
                        ->default(true),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->helperText('Kapatılırsa bu bayinin onay yetkisi ve (il ise) gelir payı hesaplaması anında durur.'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Not')
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Bayi')
                    ->description(fn (RegionDealer $r) => $r->user?->phone)
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('region_type')
                    ->label('Tür')
                    ->formatStateUsing(fn ($state) => $state === 'il' ? 'İl Bayisi' : 'İlçe Bayisi')
                    ->colors(['primary' => 'il', 'gray' => 'ilce']),

                Tables\Columns\TextColumn::make('il')
                    ->label('İl')
                    ->searchable(),

                Tables\Columns\TextColumn::make('ilce')
                    ->label('İlçe')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('revenue_share_percent')
                    ->label('Gelir Payı')
                    ->formatStateUsing(fn ($state, RegionDealer $r) => $r->isIl() ? "%{$state}" : '—'),

                Tables\Columns\IconColumn::make('can_approve_demands')
                    ->label('Talep')
                    ->boolean(),

                Tables\Columns\IconColumn::make('can_approve_offers')
                    ->label('Teklif')
                    ->boolean(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Atandı')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('region_type')
                    ->label('Tür')
                    ->options(['il' => 'İl Bayisi', 'ilce' => 'İlçe Bayisi']),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif mi'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()->label('Sil'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Seçilenleri Sil'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRegionDealers::route('/'),
            'create' => Pages\CreateRegionDealer::route('/create'),
            'edit'   => Pages\EditRegionDealer::route('/{record}/edit'),
        ];
    }
}
