<?php

namespace App\Filament\Admin\Resources\UserResource\RelationManagers;

use App\Models\PortfolioItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemOfferGrantsRelationManager extends RelationManager
{
    protected static string $relationship = 'itemOfferGrants';

    protected static ?string $title = 'Aktif Teklif Hakları';

    protected static ?string $modelLabel = 'Hak';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('portfolio_item_id')
                    ->label('Portföy Öğesi')
                    ->options(fn () => PortfolioItem::query()
                        ->where('user_id', $this->getOwnerRecord()->id)
                        ->pluck('title', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Başlangıç')
                    ->default(now())
                    ->required(),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('Bitiş')
                    ->default(now()->addDays(30))
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('portfolioItem.title')
                    ->label('Portföy Öğesi')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Başlangıç')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Bitiş')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif mi')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->ends_at?->isFuture())
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('walletTransaction.description')
                    ->label('Kaynak İşlem')
                    ->limit(25)
                    ->placeholder('—'),
            ])
            ->defaultSort('ends_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->label('Sadece Aktifler')
                    ->query(fn ($query) => $query->where('ends_at', '>', now())),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Manuel Hak Tanımla'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()->label('Sil'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
