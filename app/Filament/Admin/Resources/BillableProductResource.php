<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BillableProductResource\Pages;
use App\Models\BillableProduct;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BillableProductResource extends Resource
{
    protected static ?string $model            = BillableProduct::class;
    protected static ?string $navigationLabel   = 'Ödenebilir Ürünler';
    protected static ?string $navigationIcon    = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup   = 'Ödeme & Abonelik';
    protected static ?string $modelLabel        = 'Ürün / Plan';
    protected static ?string $pluralModelLabel  = 'Ödenebilir Ürünler';
    protected static ?int    $navigationSort    = 2;

    /** Bayilik sistemi: ürün/fiyatlandırma yönetimi sadece admin görür. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Temel Bilgiler')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Ürün Adı')
                        ->placeholder('Örn: Temel Abonelik, Premium Abonelik, 10 Kontör Paketi')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                            if ($operation === 'create') {
                                $set('code', Str::slug($state, '_'));
                            }
                        }),

                    Forms\Components\TextInput::make('code')
                        ->label('Kod')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Sistem içi benzersiz anahtar, ödeme/checkout isteklerinde bu kod kullanılır.'),

                    Forms\Components\Select::make('type')
                        ->label('Tür')
                        ->options([
                            'subscription'     => 'Abonelik (tekrarlayan dönem)',
                            'credit_pack'      => 'Kontör Paketi (tek seferlik)',
                            'featured_listing' => 'Öne Çıkan İlan',
                            'boost'            => 'İlan Öne Çıkarma (Boost)',
                        ])
                        ->required()
                        ->native(false)
                        ->live(),

                    Forms\Components\TextInput::make('price')
                        ->label('Fiyat (₺)')
                        ->numeric()
                        ->prefix('₺')
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->helperText('Kapalıyken bu ürün /subscription/plans listesinde ve checkout\'ta görünmez.'),
                ])->columns(2),

            Forms\Components\Section::make('Abonelik Kotaları')
                ->description('Sadece "Abonelik" türü ürünler için geçerlidir.')
                ->visible(fn (Forms\Get $get) => $get('type') === 'subscription')
                ->schema([
                    Forms\Components\TextInput::make('duration_days')
                        ->label('Dönem Süresi (gün)')
                        ->numeric()
                        ->default(30)
                        ->helperText('Ödeme onaylandıktan sonra abonelik kaç gün aktif kalır.'),

                    Forms\Components\TextInput::make('offer_quota')
                        ->label('Dönemlik Teklif Kotası')
                        ->numeric()
                        ->helperText('Boş bırakılırsa sınırsız teklif hakkı verilir.'),

                    Forms\Components\TextInput::make('portfolio_limit_override')
                        ->label('Portföy Limiti (override)')
                        ->numeric()
                        ->helperText('Hesap grubunun normal portföy limitini bu abonelik süresince geçersiz kılar. Boş bırakılırsa hesap grubunun limiti geçerli olmaya devam eder.'),

                    Forms\Components\Toggle::make('unlimited_portfolio')
                        ->label('Sınırsız Portföy')
                        ->helperText('Açıksa, bu abonelik süresince portföy kaydı sınırı tamamen kaldırılır.'),
                ])->columns(2),

            Forms\Components\Section::make('Kontör Miktarı')
                ->description('Sadece "Kontör Paketi" türü ürünler için geçerlidir.')
                ->visible(fn (Forms\Get $get) => $get('type') === 'credit_pack')
                ->schema([
                    Forms\Components\TextInput::make('credit_amount')
                        ->label('Kontör Miktarı')
                        ->numeric(),
                ]),

            Forms\Components\Section::make('Kategori Kısıtı')
                ->description('Boş bırakılırsa ürün tüm kategorilerde geçerli olur.')
                ->schema([
                    Forms\Components\Select::make('categories')
                        ->label('Sadece Şu Kategorilerde Geçerli')
                        ->options(fn () => Category::query()->leaves()->pluck('name', 'slug'))
                        ->multiple()
                        ->searchable()
                        // Boş bırakılırsa DB'de [] değil null olarak saklanmalı — kod
                        // tarafında "null = tüm kategoriler" anlamına geliyor
                        // (bkz. User::canOfferInCategory()), boş dizi ise "hiçbir
                        // kategoride geçerli değil" anlamına gelir ve yanlış olur.
                        ->dehydrateStateUsing(fn ($state) => empty($state) ? null : array_values($state))
                        ->helperText('Örn: sadece "vasita" ve "gayrimenkul" kategorilerinde geçerli bir plan tanımlamak için seçin.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ürün Adı')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Kod')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tür')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'subscription'     => 'Abonelik',
                        'credit_pack'      => 'Kontör Paketi',
                        'featured_listing' => 'Öne Çıkan İlan',
                        'boost'            => 'Boost',
                        default            => $state,
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->label('Fiyat')
                    ->money('TRY'),

                Tables\Columns\TextColumn::make('offer_quota')
                    ->label('Teklif Kotası')
                    ->formatStateUsing(fn ($state) => $state === null ? 'Sınırsız' : $state),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->requiresConfirmation()
                    ->modalDescription('Bu ürüne bağlı ödeme/abonelik kayıtları varsa silme veritabanı hatası verebilir; önce pasife almanız önerilir.'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Seçilenleri Sil'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBillableProducts::route('/'),
            'create' => Pages\CreateBillableProduct::route('/create'),
            'edit'   => Pages\EditBillableProduct::route('/{record}/edit'),
        ];
    }
}
