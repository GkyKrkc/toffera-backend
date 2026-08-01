<?php

namespace App\Filament\Admin\Resources\AccountTypeGroupResource\RelationManagers;

use App\Models\AccountTypeGroup;
use App\Models\Category;
use App\Services\CategoryAccessService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';
    protected static ?string $title       = 'Kategoriler';
    protected static ?string $label       = 'Kategori';
    protected static ?string $pluralLabel = 'Kategoriler';

    /**
     * Pivot alanları — hem "Portföy Limiti" hem "Teklif Verme İzni" burada.
     * can_offer: bu gruptaki bir kullanıcı bu kategoride varsayılan olarak
     * teklif verebilsin mi (user_category_permissions.can_offer'a senkronize
     * edilir — bkz. CategoryAccessService::syncFromGroup()).
     */
    private static function pivotFields(): array
    {
        return [
            Forms\Components\TextInput::make('portfolio_limit')
                ->label('Portföy Limiti')
                ->numeric()
                ->minValue(1)
                ->placeholder('Boş = sınırsız')
                ->helperText('Bu gruba ait bir kullanıcının bu kategoride ekleyebileceği en fazla portföy öğesi sayısı. Boş bırakılırsa sınırsız olur.'),

            Forms\Components\Toggle::make('can_offer')
                ->label('Teklif Verme İzni')
                ->default(false)
                ->helperText('Açıksa, bu gruptaki kullanıcılar bu kategorideki taleplere teklif verebilir (kontör/abonelik kotası ayrıca kontrol edilir).'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema(self::pivotFields());
    }

    /**
     * Kaydeden her aksiyondan sonra (seç-ekle/edit/detach) bu gruptaki TÜM
     * kullanıcıların user_category_permissions'ını yeniden senkronize eder.
     * Böylece admin panelden yapılan bir değişiklik, o gruba önceden
     * atanmış kullanıcılara da otomatik yansır.
     */
    private function resyncGroup(): void
    {
        /** @var AccountTypeGroup $group */
        $group = $this->getOwnerRecord();
        app(CategoryAccessService::class)->syncAllUsersInGroup($group);
    }

    /**
     * Sol paneldeki GERÇEK ağaç menü — dal/kök düğümler açılır/kapanır
     * (expand/collapse ok işareti), her seviyede checkbox var: yaprak
     * kategoriler tek tek işaretlenebilir, dal/kök checkbox'ı ise kendi
     * altındaki TÜM yaprakları tek tıkla işaretler/kaldırır (bkz.
     * resources/views/filament/forms/components/category-tree-*.blade.php).
     * Kategori ağacı DEĞİŞSE bile (yeni kök/dal eklense de) bu kod hardcode
     * olmadan otomatik uyum sağlar — sadece düz nested array üretiyor,
     * görünüm tamamen Blade+Alpine tarafında.
     *
     * @return array<int, array{id:int,name:string,children:array,leaf_ids:array<int>}>
     */
    private function categoryTreeData(): array
    {
        return Category::whereNull('parent_id')
            ->with('childrenRecursive')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $root) => $this->buildCategoryTreeNode($root))
            ->all();
    }

    private function buildCategoryTreeNode(Category $category): array
    {
        $children = $category->children->sortBy('sort_order')->values();

        if ($children->isEmpty()) {
            return [
                'id'       => $category->id,
                'name'     => $category->name,
                'children' => [],
                'leaf_ids' => [$category->id],
            ];
        }

        $childNodes = $children->map(fn (Category $child) => $this->buildCategoryTreeNode($child))->all();

        return [
            'id'       => $category->id,
            'name'     => $category->name,
            'children' => $childNodes,
            'leaf_ids' => collect($childNodes)->flatMap(fn (array $node) => $node['leaf_ids'])->values()->all(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kategori Adı')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Üst Kategori')
                    ->placeholder('— Ana Kategori —'),

                Tables\Columns\TextColumn::make('pivot.portfolio_limit')
                    ->label('Portföy Limiti')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === null ? 'Sınırsız' : $state . ' adet')
                    ->color(fn ($state) => $state === null ? 'success' : 'gray'),

                Tables\Columns\IconColumn::make('pivot.can_offer')
                    ->label('Teklif İzni')
                    ->boolean(),
            ])
            ->filters([])
            ->headerActions([
                // Eski AttachAction (tek tek, dropdown'dan arayarak seçme)
                // YERİNE: sol tarafta ağaç görünümlü çoklu-checkbox seçim
                // paneli, sağ tarafta paylaşılan Portföy Limiti/Teklif İzni
                // alanları. İşaretlenen TÜM kategoriler, aynı limit/izin
                // değerleriyle tek seferde eklenir/güncellenir (syncWithoutDetaching
                // — daha önce eklenmiş, burada işaretlenmeyen kategorilere
                // dokunulmaz).
                Tables\Actions\Action::make('selectCategories')
                    ->label('Kategori Seç')
                    ->icon('heroicon-o-squares-2x2')
                    ->modalHeading('Teklif Verilecek Kategorileri Seç')
                    ->modalDescription('Soldaki ağaçtan bu gruba eklenecek/güncellenecek kategorileri işaretleyin, sağdan ortak limit ve teklif iznini ayarlayın.')
                    ->modalWidth('5xl')
                    ->modalSubmitActionLabel('Seçilenleri Kaydet')
                    ->form([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\ViewField::make('selected_categories')
                                    ->view('filament.forms.components.category-tree-select')
                                    ->viewData(['roots' => $this->categoryTreeData()])
                                    ->default([])
                                    ->dehydrated(true)
                                    ->columnSpan(2),
                                Forms\Components\Group::make(self::pivotFields())
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->action(function (array $data): void {
                        /** @var Collection $selectedIds */
                        $selectedIds = collect($data['selected_categories'] ?? [])
                            ->filter(fn ($checked) => (bool) $checked)
                            ->keys()
                            ->map(fn ($id) => (int) $id);

                        if ($selectedIds->isEmpty()) {
                            Notification::make()
                                ->title('En az bir kategori seçmelisiniz.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $pivotData = [
                            'portfolio_limit' => $data['portfolio_limit'] ?? null,
                            'can_offer'       => $data['can_offer'] ?? false,
                        ];

                        /** @var AccountTypeGroup $group */
                        $group = $this->getOwnerRecord();

                        foreach ($selectedIds as $categoryId) {
                            $group->categories()->syncWithoutDetaching([$categoryId => $pivotData]);
                        }

                        $this->resyncGroup();

                        Notification::make()
                            ->title($selectedIds->count() . ' kategori eklendi/güncellendi.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->form(self::pivotFields())
                    ->after(fn () => $this->resyncGroup()),
                Tables\Actions\DetachAction::make()
                    ->label('Kaldır')
                    ->after(fn () => $this->resyncGroup()),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()
                    ->label('Seçilenleri Kaldır')
                    ->after(fn () => $this->resyncGroup()),
            ]);
    }
}
