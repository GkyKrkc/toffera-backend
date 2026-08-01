<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankAccountResource\Pages;
use App\Models\BankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

// Havale/EFT ile ödeme yapmak isteyen kullanıcılara gösterilecek şirket
// banka hesapları. QuickMessageResource ile aynı desen: admin deploy
// gerekmeden buradan ekleyip/düzenleyebilir/sıralayabilir. Frontend
// bunları GET /api/bank-accounts üzerinden okur (bkz. PaymentController).
// Tek hesapla sınırlı DEĞİL — şirketin birden çok bankada hesabı olabilir,
// hepsi burada listelenir ve kullanıcı checkout sırasında birini seçer.
class BankAccountResource extends Resource
{
    protected static ?string $model            = BankAccount::class;
    protected static ?string $navigationLabel   = 'Banka Hesapları';
    protected static ?string $navigationIcon    = 'heroicon-o-building-library';
    protected static ?string $navigationGroup   = 'Ödeme & Abonelik';
    protected static ?string $modelLabel        = 'Banka Hesabı';
    protected static ?string $pluralModelLabel  = 'Banka Hesapları';
    protected static ?int    $navigationSort    = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Banka Hesabı')
                ->description('Havale/EFT ile ödeme seçen kullanıcılara gösterilir. IBAN, hesap sahibi ismiyle birebir eşleşmelidir.')
                ->schema([
                    Forms\Components\TextInput::make('banka_adi')
                        ->label('Banka Adı')
                        ->placeholder('Örn: Türkiye İş Bankası')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('hesap_sahibi')
                        ->label('Hesap Sahibi')
                        ->placeholder('IBAN üzerindeki isimle birebir aynı olmalı')
                        ->required()
                        ->maxLength(150),

                    Forms\Components\TextInput::make('iban')
                        ->label('IBAN')
                        ->placeholder('TR00 0000 0000 0000 0000 0000 00')
                        ->required()
                        ->maxLength(40),

                    Forms\Components\TextInput::make('sube')
                        ->label('Şube (opsiyonel)')
                        ->maxLength(120),

                    Forms\Components\Textarea::make('aciklama')
                        ->label('Not (opsiyonel)')
                        ->placeholder('Örn: Sadece TL hesabı, kurumsal ödemeler için')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sıra')
                        ->numeric()
                        ->default(0),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->helperText('Kapatılırsa Havale/EFT seçeneğinde kullanıcıya gösterilmez.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('banka_adi')
                    ->label('Banka')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('hesap_sahibi')
                    ->label('Hesap Sahibi')
                    ->searchable(),

                Tables\Columns\TextColumn::make('iban')
                    ->label('IBAN')
                    ->copyable()
                    ->fontFamily('mono'),

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
            'index'  => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit'   => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}
