<?php

namespace App\Filament\Admin\Resources\CategoryResource\RelationManagers;

use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Alt Kategoriler';

    protected static ?string $modelLabel = 'Alt Kategori';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Ad')
                ->required()
                ->maxLength(255)
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
                ->maxLength(255),

            Forms\Components\Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->searchable(),

                Tables\Columns\TextColumn::make('children_count')
                    ->label('Alt Kategori')
                    ->counts('children')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Yeni Alt Kategori'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->url(fn (Model $record): string => \App\Filament\Admin\Resources\CategoryResource::getUrl('edit', ['record' => $record])),

                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->requiresConfirmation()
                    ->modalDescription('Bu kategoriyi silersen, altındaki alt kategoriler ÖKSÜZ kalır (ana kategoriye dönüşür), silinmez. Bu kategoriye bağlı talepler varsa silme engellenir.')
                    ->before(function (Model $record) {
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
}
