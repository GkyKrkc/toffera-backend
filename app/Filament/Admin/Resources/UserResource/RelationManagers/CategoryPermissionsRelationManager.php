<?php

namespace App\Filament\Admin\Resources\UserResource\RelationManagers;

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryAccessService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Bir kullanıcının kategori bazlı yetkilerini gösterir ve elle override
 * etmeye izin verir. Buradan yapılan HER değişiklik source='manual' olarak
 * işaretlenir — bir sonraki grup senkronu (CategoryAccessService::
 * syncFromGroup) bu satırlara DOKUNMAZ. "Grup Varsayılanına Sıfırla"
 * aksiyonu, satırı silip senkronu tekrar tetikleyerek source='group'a
 * geri döndürür.
 */
class CategoryPermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'categoryPermissions';
    protected static ?string $title       = 'Kategori Yetkileri';
    protected static ?string $label       = 'Kategori Yetkisi';
    protected static ?string $pluralLabel = 'Kategori Yetkileri';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('category_id')
                ->label('Kategori')
                ->options(fn () => Category::whereDoesntHave('children')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->disabled(fn (?\App\Models\UserCategoryPermission $record) => $record !== null)
                ->helperText('Kayıt oluşturulduktan sonra kategori değiştirilemez, silip yeniden ekleyin.'),

            Forms\Components\Toggle::make('can_add_portfolio')
                ->label('Portföy Ekleyebilir')
                ->default(true),

            Forms\Components\TextInput::make('portfolio_limit')
                ->label('Portföy Limiti')
                ->numeric()
                ->minValue(1)
                ->placeholder('Boş = sınırsız'),

            Forms\Components\Toggle::make('can_offer')
                ->label('Teklif Verme İzni')
                ->default(false),
        ]);
    }

    /** Bu panelden yapılan her create/update source='manual' işaretlenir — grup senkronu ezmesin diye. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = 'manual';
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['source'] = 'manual';
        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('category.name')
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('can_add_portfolio')
                    ->label('Portföy Ekleyebilir')
                    ->boolean(),

                Tables\Columns\TextColumn::make('portfolio_limit')
                    ->label('Limit')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === null ? 'Sınırsız' : $state . ' adet')
                    ->color(fn ($state) => $state === null ? 'success' : 'gray'),

                Tables\Columns\IconColumn::make('can_offer')
                    ->label('Teklif İzni')
                    ->boolean(),

                Tables\Columns\BadgeColumn::make('source')
                    ->label('Kaynak')
                    ->formatStateUsing(fn ($state) => $state === 'manual' ? 'Elle Ayarlandı' : 'Gruptan')
                    ->colors([
                        'warning' => 'manual',
                        'gray'    => 'group',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Kategori Yetkisi Ekle'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle'),

                Tables\Actions\Action::make('resetToGroup')
                    ->label('Grup Varsayılanına Sıfırla')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Bu elle yapılan ayarı silip, kullanıcının grubundaki (varsa) varsayılan değerlere geri döndürür.')
                    ->visible(fn (\App\Models\UserCategoryPermission $record) => $record->source === 'manual')
                    ->action(function (\App\Models\UserCategoryPermission $record) {
                        /** @var User $user */
                        $user = $this->getOwnerRecord();
                        $record->delete();
                        app(CategoryAccessService::class)->syncFromGroup($user);
                        Notification::make()->title('Grup varsayılanına sıfırlandı.')->success()->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Sil'),
            ]);
    }
}
