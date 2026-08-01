<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\QuickMessageResource\Pages;
use App\Models\QuickMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

// Mesajlaşma panelinde tek tıkla gönderilebilen hazır mesaj önerileri
// ("Fiyatta pazarlık var mı?", "%10 indirim talep ediyorum" vb.) — admin
// deploy gerekmeden buradan ekleyip/düzenleyebilir. Frontend bunları
// GET /api/quick-messages üzerinden okur (bkz. QuickMessageController).
class QuickMessageResource extends Resource
{
    protected static ?string $model            = QuickMessage::class;
    protected static ?string $navigationLabel   = 'Hazır Mesajlar';
    protected static ?string $navigationIcon    = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup   = 'İçerik Yönetimi';
    protected static ?string $modelLabel        = 'Hazır Mesaj';
    protected static ?string $pluralModelLabel  = 'Hazır Mesajlar';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Hazır Mesaj')
                ->description('Mesajlaşma panelinde, metin kutusunun üstünde tek tıkla gönderilebilen küçük çip olarak gösterilir.')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('Çip Metni')
                        ->placeholder('Örn: %10 indirim talep ediyorum')
                        ->required()
                        ->maxLength(60),

                    Forms\Components\Textarea::make('body')
                        ->label('Gönderilecek Mesaj')
                        ->placeholder('Örn: Fiyatta pazarlık payı var mı? %10 indirim talep ediyorum.')
                        ->helperText('Kullanıcı çipe tıkladığında karşı tarafa gönderilecek asıl metin.')
                        ->required()
                        ->rows(2)
                        ->maxLength(2000),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sıra')
                        ->numeric()
                        ->default(0),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->helperText('Kapatılırsa mesajlaşma panelinde gösterilmez.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Çip Metni')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('body')
                    ->label('Mesaj')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
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
            'index'  => Pages\ListQuickMessages::route('/'),
            'create' => Pages\CreateQuickMessage::route('/create'),
            'edit'   => Pages\EditQuickMessage::route('/{record}/edit'),
        ];
    }
}
