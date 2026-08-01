<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AccountTypeGroupResource\Pages;
use App\Filament\Admin\Resources\AccountTypeGroupResource\RelationManagers\CategoriesRelationManager;
use App\Models\AccountTypeGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AccountTypeGroupResource extends Resource
{
    protected static ?string $model            = AccountTypeGroup::class;
    protected static ?string $navigationLabel   = 'Hesap Grupları';
    protected static ?string $navigationIcon    = 'heroicon-o-user-group';
    protected static ?string $navigationGroup   = 'Kategori Yönetimi';
    protected static ?string $modelLabel        = 'Hesap Grubu';
    protected static ?string $pluralModelLabel  = 'Hesap Grupları';
    protected static ?int    $navigationSort    = 2;

    /** Bayilik sistemi: hesap grubu ayarları sadece admin görür/düzenler. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Temel Bilgiler')
                ->description('Kayıt sırasında bireysel kullanıcıya otomatik atanır ya da ticari kullanıcı seçer. Bu grubun hangi kategorilere, kaç adet portföy ekleyebileceği ise altındaki "Kategoriler" sekmesinden yönetilir (kaydettikten sonra görünür).')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Grup Adı')
                        ->placeholder('Örn: Galericiler (2. El), Plazalar (Sıfır Araç), Rent A Car, Bireysel Talep')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('API ve kayıt formunda kullanılır, benzersiz olmalı.'),

                    Forms\Components\Select::make('kind')
                        ->label('Tür')
                        ->options([
                            'individual' => 'Bireysel — kayıtta otomatik atanır, belge istenmez',
                            'commercial' => 'Ticari — kayıtta kullanıcı seçer, kategoriye bağlı belge istenir',
                        ])
                        ->required()
                        ->native(false)
                        ->helperText('Dikkat: yanlış seçilirse bireysel kullanıcılardan gereksiz yere firma adı/belge istenir.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->helperText('Kapatılırsa bu grup kayıt formunda seçilemez.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Grup Adı')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('kind')
                    ->label('Tür')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'individual' ? 'Bireysel' : 'Ticari')
                    ->color(fn (string $state) => $state === 'individual' ? 'gray' : 'primary'),

                Tables\Columns\TextColumn::make('categories_count')
                    ->label('Bağlı Kategori')
                    ->counts('categories')
                    ->suffix(' adet'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Kullanıcı')
                    ->counts('users')
                    ->suffix(' kişi'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('kind')
                    ->label('Tür')
                    ->options([
                        'individual' => 'Bireysel',
                        'commercial' => 'Ticari',
                    ]),
            ])
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->requiresConfirmation()
                    ->modalDescription('Bu gruba atanmış kullanıcılar varsa silme engellenir.')
                    ->before(function (AccountTypeGroup $record) {
                        if ($record->users()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Bu gruba atanmış kullanıcılar var, silinemez.')
                                ->danger()
                                ->send();

                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Seçilenleri Sil'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CategoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAccountTypeGroups::route('/'),
            'create' => Pages\CreateAccountTypeGroup::route('/create'),
            'edit'   => Pages\EditAccountTypeGroup::route('/{record}/edit'),
        ];
    }
}
