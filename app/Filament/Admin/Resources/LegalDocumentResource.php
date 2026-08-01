<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\LegalDocumentResource\Pages;
use App\Models\LegalDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Yasal metinler (Kullanıcı Sözleşmesi, KVKK Aydınlatma Metni, Açık Rıza
 * Metni, Ticari Elektronik İleti Onayı). Sabit 4 satır — LegalDocumentSeeder
 * ile oluşturulur, admin panelden YENİ satır EKLENEMEZ/SİLİNEMEZ (sadece
 * mevcut 4 tanesi düzenlenir), o yüzden ne Create ne Delete action'ı var.
 * body güncellenince version otomatik artar (bkz.
 * Pages\EditLegalDocument::handleRecordUpdate()).
 */
class LegalDocumentResource extends Resource
{
    protected static ?string $model            = LegalDocument::class;
    protected static ?string $navigationLabel  = 'Yasal Metinler';
    protected static ?string $navigationIcon   = 'heroicon-o-scale';
    protected static ?string $navigationGroup  = 'Sistem';
    protected static ?string $modelLabel       = 'Yasal Metin';
    protected static ?string $pluralModelLabel = 'Yasal Metinler';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(?\Illuminate\Database\Eloquent\Model $record = null): bool
    {
        return false;
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            LegalDocument::TYPE_USER_AGREEMENT   => 'Kullanıcı Sözleşmesi',
            LegalDocument::TYPE_KVKK_DISCLOSURE   => 'KVKK Aydınlatma Metni',
            LegalDocument::TYPE_EXPLICIT_CONSENT  => 'Açık Rıza Metni',
            LegalDocument::TYPE_COMMERCIAL_MSG    => 'Ticari Elektronik İleti Onayı',
            default                                => $type,
        };
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\TextInput::make('type')
                        ->label('Tip')
                        ->formatStateUsing(fn (string $state) => self::typeLabel($state))
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('title')
                        ->label('Başlık')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Toggle::make('is_mandatory')
                        ->label('Üyelikte Zorunlu')
                        ->helperText('Bu değer sistem tarafından belirlenir, buradan değiştirilemez — sadece bilgi amaçlıdır.')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Textarea::make('body')
                        ->label('Metin')
                        ->required()
                        ->rows(20)
                        ->columnSpanFull()
                        ->helperText(
                            'Kullanılabilir yer tutucular: {sirket_unvani}, {sirket_adresi}, {sirket_telefon}, ' .
                            '{sirket_email}, {sirket_faks}, {mersis_no}, {vergi_dairesi}, {vergi_no}, {kep_adresi}, ' .
                            '{bugun}, {kullanici_adi}, {kullanici_email}. Bu alanlar kaydedilirken gerçek değerlerle ' .
                            'değiştirilir (bkz. Kurumsal Bilgiler sayfası). Metni değiştirip kaydettiğinizde versiyon ' .
                            'otomatik artar ve zorunlu metinlerde mevcut kullanıcılardan yeniden onay istenir.'
                        ),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->formatStateUsing(fn (string $state) => self::typeLabel($state))
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_mandatory')
                    ->label('Zorunlu')
                    ->boolean(),

                Tables\Columns\TextColumn::make('version')
                    ->label('Versiyon')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Son Güncelleme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegalDocuments::route('/'),
            'edit'  => Pages\EditLegalDocument::route('/{record}/edit'),
        ];
    }
}
