<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CategoryResource\Pages;
use App\Filament\Admin\Resources\CategoryResource\RelationManagers\ChildrenRelationManager;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model            = Category::class;
    protected static ?string $navigationLabel  = 'Kategoriler';
    protected static ?string $navigationIcon   = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup  = 'Kategori Yönetimi';
    protected static ?string $modelLabel       = 'Kategori';
    protected static ?string $pluralModelLabel = 'Kategoriler';
    protected static ?int    $navigationSort   = 1;

    /** Bayilik sistemi: kategori yönetimi sadece admin görür/düzenler. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Temel Bilgiler')
                ->schema([
                    Forms\Components\Select::make('parent_id')
                        ->label('Üst Kategori')
                        ->relationship(
                            name: 'parent',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn(Builder $query, ?Category $record) =>
                            $record ? $query->whereNot('id', $record->id) : $query
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        ->placeholder('— Ana kategori (üst yok) —')
                        ->helperText('Boş bırakılırsa bu bir ANA kategori olur.'),

                    Forms\Components\TextInput::make('name')
                        ->label('Kategori Adı')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug($state));
                            }
                        })
                        ->datalist(fn () => Category::query()
                            ->orderBy('name')
                            ->pluck('name')
                            ->unique()
                            ->values()
                            ->toArray()),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('URL ve API için kullanılır, benzersiz olmalı.'),

                    Forms\Components\TextInput::make('icon')
                        ->label('İkon')
                        ->placeholder('Örn: building-2, car, cpu (lucide-react ikon adı)'),

                    Forms\Components\Select::make('form_component')
                        ->label('Kullanılacak Form')
                        ->options(function (Get $get, ?Category $record) {
                            // Bu kategorinin KÖK atasına göre hangi formların anlamlı
                            // olduğunu belirliyoruz: "vasita" ağacındaki bir kategoriye
                            // "real_estate" formu (ya da tersi) atanamaz — aksi halde o
                            // kategoriye izinli kullanıcı, hesap tipiyle hiç alakası
                            // olmayan formun add sayfasına (ör. Vasıta Uzmanı →
                            // /portfolio/realestate/add) erişebiliyordu.
                            $parentId = $get('parent_id') ?? $record?->parent_id;
                            $root     = $parentId ? Category::find($parentId)?->root() : $record?->root();

                            // Vasita/Gayrimenkul dışındaki ağaçlarda (ör. Elektronik > Cep
                            // Telefonu) bu iki zengin form hiç anlamlı değil — seçilebilir
                            // olmamalı. O kategoriler için "Jenerik (Otomatik Form)" + aşağıdaki
                            // "Form Alanları (Dinamik)" (form_schema) kullanılmalı.
                            return match ($root?->slug) {
                                'vasita'      => ['vehicle' => 'Vasıta (Zengin Form) — hasar şeması, plaka, TAKAS'],
                                'gayrimenkul' => ['real_estate' => 'Gayrimenkul (Zengin Form) — adres, m², oda sayısı vb.'],
                                default       => [],
                            };
                        })
                        ->native(false)
                        ->nullable()
                        ->placeholder('Jenerik (Otomatik Form) — başlık/açıklama/fiyat + aşağıdaki dinamik alanlar')
                        ->helperText('Bu kategoride portföy/ilan eklerken kullanıcıya hangi formun açılacağını belirler. Boş bırakılırsa jenerik form + aşağıdaki "Form Alanları" kullanılır. Sadece YAPRAK kategorilerde anlamlıdır. Vasıta/Gayrimenkul dışındaki kategori ağaçlarında bu iki zengin form seçilemez — onlar için aşağıdaki dinamik alanları tanımlayın.')
                        ->visible(fn (?Category $record) => !$record || $record->children()->doesntExist()),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->helperText('Kapatılırsa bu kategori (ve altındakiler) pazaryerinde ve talep formunda görünmez.'),
                ])->columns(2),

            Forms\Components\Section::make('Form Alanları (Dinamik)')
                ->description('Sadece YAPRAK kategorilerde (alt kategorisi olmayan) kullanılır. Bu kategoride talep/portföy oluşturulurken kullanıcıya gösterilecek özel alanları tanımlar. Alanları sürükleyerek sırasını değiştirebilir, "Bölüm" ile gruplayabilirsiniz — sağdaki canlı önizleme aynı sırayla/aynı gruplarla güncellenir.')
                ->schema([
                    Forms\Components\Grid::make(12)
                        ->schema([
                            Forms\Components\Group::make([
                                Forms\Components\Repeater::make('form_schema')
                                    ->label('')
                                    ->live()
                                    ->schema([
                                        Forms\Components\TextInput::make('label')
                                            ->label('Alan Adı (Kullanıcıya Görünen)')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn ($state, Set $set) => $set('key', Str::slug($state ?? '', '_')))
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('key')
                                            ->label('Alan Anahtarı (key)')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->helperText('API/veritabanında kullanılacak benzersiz anahtar.')
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('section')
                                            ->label('Bölüm (opsiyonel)')
                                            ->live(onBlur: true)
                                            ->helperText('Aynı bölüm adını yazdığınız alanlar önizlemede ve formda birlikte başlık altında gösterilir. Boş bırakılırsa gruplanmaz.')
                                            ->datalist(fn (Get $get) => collect($get('../../form_schema') ?? [])
                                                ->pluck('section')
                                                ->filter()
                                                ->unique()
                                                ->values()
                                                ->toArray())
                                            ->columnSpan(2),

                                        Forms\Components\Select::make('type')
                                            ->label('Alan Tipi')
                                            ->options([
                                                'text'     => 'Metin',
                                                'textarea' => 'Uzun Metin',
                                                'number'   => 'Sayı',
                                                'select'   => 'Açılır Liste',
                                                'radio'    => 'Tek Seçim (Radio)',
                                                'checkbox' => 'Çoklu Seçim (Checkbox)',
                                                'date'     => 'Tarih',
                                            ])
                                            ->required()
                                            ->live()
                                            ->columnSpan(2),

                                        Forms\Components\Toggle::make('required')
                                            ->label('Zorunlu')
                                            ->default(false)
                                            ->live()
                                            ->columnSpan(1),

                                        Forms\Components\TagsInput::make('options')
                                            ->label('Seçenekler')
                                            ->helperText('Her seçeneği yazıp Enter\'a bas.')
                                            ->live()
                                            ->visible(fn (Get $get) => in_array($get('type'), ['select', 'radio', 'checkbox']))
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(6)
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(fn (array $state): ?string => trim(($state['section'] ? "[{$state['section']}] " : '') . ($state['label'] ?? '')) ?: null)
                                    ->addActionLabel('Yeni Alan Ekle')
                                    ->reorderable()
                                    ->defaultItems(0),
                            ])->columnSpan(7),

                            Forms\Components\Placeholder::make('form_schema_preview')
                                ->label('Canlı Önizleme')
                                ->helperText('Solda alan ekledikçe, düzenledikçe ya da sürükleyip sıraladıkça burası anında güncellenir — kullanıcının talep/portföy formunda göreceği hâl budur.')
                                ->content(fn (Get $get) => new HtmlString(view('filament.admin.category-form-preview', [
                                    'fields' => $get('form_schema') ?? [],
                                ])->render()))
                                ->columnSpan(5),
                        ]),
                ])
                ->visible(fn (?Category $record) => !$record || $record->children()->doesntExist())
                ->collapsible(),

            Forms\Components\Section::make('Gerekli Belgeler (Yetkilendirme)')
                ->description('Bu kategoride agent/acente yetkilendirme başvurusunda istenecek belgeler. Genelde ana kategori seviyesinde doldurulur; alt kategoriler üst kategoriden miras almaz, bağımsız bir liste tutar.')
                ->schema([
                    Forms\Components\Repeater::make('required_documents')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->label('Belge Adı')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Set $set) => $set('key', Str::slug($state ?? '', '_')))
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('key')
                                ->label('Belge Anahtarı (key)')
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\Toggle::make('required')
                                ->label('Zorunlu')
                                ->default(true)
                                ->columnSpan(1),
                        ])
                        ->columns(5)
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->addActionLabel('Yeni Belge Ekle')
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->defaultItems(0),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kategori Adı')
                    ->searchable()
                    ->weight(fn(Category $record) => $record->parent_id ? null : 'bold'),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Üst Kategori')
                    ->placeholder('— Ana Kategori —')
                    ->badge()
                    ->color(fn($state) => $state ? 'gray' : 'primary'),

                Tables\Columns\TextColumn::make('children_count')
                    ->label('Alt Kategori')
                    ->counts('children')
                    ->suffix(' adet'),

                Tables\Columns\IconColumn::make('is_leaf_display')
                    ->label('Tür')
                    ->getStateUsing(fn(Category $record) => $record->isLeaf())
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-folder')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn(Category $record) => $record->isLeaf() ? 'Yaprak (form burada tanımlanır)' : 'Dal (organizasyon amaçlı)'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('form_component')
                    ->label('Form')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'vehicle'     => 'Vasıta',
                        'real_estate' => 'Gayrimenkul',
                        default       => 'Jenerik',
                    })
                    ->color(fn ($state) => $state ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Üst Kategoriye Göre Göster')
                    ->options(
                        Category::roots()->pluck('name', 'id')
                    )
                    ->default(null)
                    ->query(function (Builder $query, array $data) {
                        // Filtre seçilmemişse varsayılan: sadece ana kategorileri göster.
                        if (blank($data['value'])) {
                            $query->whereNull('parent_id');
                            return;
                        }
                        $query->where('parent_id', $data['value']);
                    }),
            ])
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->requiresConfirmation()
                    ->modalDescription('Bu kategoriyi silersen, altındaki alt kategoriler ÖKSÜZ kalır (ana kategoriye dönüşür), silinmez. Bu kategoriye bağlı talepler varsa silme engellenir.')
                    ->before(function (Category $record) {
                        if ($record->demands()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Bu kategoriye bağlı talepler var, silinemez.')
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
            ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit'   => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
