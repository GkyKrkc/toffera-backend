<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DealerStaffResource\Pages;
use App\Models\DealerStaff;
use App\Models\RegionDealer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bayilik departman personeli — bayi SAHİBİ kendi personelini burada
 * tanımlar (galeri/emlak/hepsi). Admin (genel merkez) tümünü görüp
 * yönetebilir. dealer_staff rolündeki kullanıcılar bu kaynağı HİÇ görmez
 * (kendi personelini yönetemez) — bkz. canViewAny().
 *
 * Telefon numarası sistemde kayıtlı değilse otomatik yeni bir hesap
 * oluşturulur (şifresiz, sadece /bayilik SMS OTP ile giriş) — bkz.
 * DealerStaffResource/Pages/CreateDealerStaff::handleRecordCreation().
 */
class DealerStaffResource extends Resource
{
    protected static ?string $model            = DealerStaff::class;
    protected static ?string $navigationLabel  = 'Departman Personeli';
    protected static ?string $navigationIcon   = 'heroicon-o-user-group';
    protected static ?string $navigationGroup  = 'Bayilik Sistemi';
    protected static ?string $modelLabel       = 'Personel';
    protected static ?string $pluralModelLabel = 'Departman Personeli';
    protected static ?int    $navigationSort   = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && ($user->hasRole('admin') || $user->hasRole('dealer'));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        // dealer: sadece KENDİ bayilik ataması/atamalarına bağlı personel.
        $dealerIds = $user?->regionDealerAssignments()->pluck('id') ?? collect();

        return $query->whereIn('region_dealer_id', $dealerIds);
    }

    public static function form(Form $form): Form
    {
        $isAdmin = auth()->user()?->hasRole('admin') ?? false;

        return $form->schema([
            Forms\Components\Section::make('Personel')
                ->description('Telefon numarası sistemde kayıtlı DEĞİLSE otomatik olarak yeni, şifresiz bir hesap oluşturulur (sadece /bayilik üzerinden SMS OTP ile giriş yapabilir). Kayıtlıysa mevcut hesaba personel yetkisi eklenir.')
                ->schema([
                    Forms\Components\Placeholder::make('mevcut_kullanici')
                        ->label('Kullanıcı')
                        ->content(fn (?DealerStaff $record) => $record ? "{$record->user?->name} — {$record->user?->phone}" : '—')
                        ->visible(fn (?DealerStaff $record) => $record !== null),

                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')
                        ->required()
                        ->regex('/^[0-9]{10,11}$/')
                        ->helperText('Örn. 5XXXXXXXXX')
                        ->visible(fn (?DealerStaff $record) => $record === null)
                        ->dehydrated(fn (?DealerStaff $record) => $record === null),

                    Forms\Components\TextInput::make('name')
                        ->label('Ad Soyad')
                        ->required()
                        ->visible(fn (?DealerStaff $record) => $record === null)
                        ->dehydrated(fn (?DealerStaff $record) => $record === null),

                    Forms\Components\Select::make('region_dealer_id')
                        ->label('Bayilik Ataması')
                        ->options(function () use ($isAdmin) {
                            $query = RegionDealer::query()->active()->with('user');
                            if (!$isAdmin) {
                                $query->where('user_id', auth()->id());
                            }
                            return $query->get()->mapWithKeys(fn (RegionDealer $r) => [
                                $r->id => ($r->isIl() ? "İl: {$r->il}" : "İlçe: {$r->il} / {$r->ilce}") . ' — ' . $r->user?->name,
                            ]);
                        })
                        ->required()
                        ->native(false)
                        ->default(fn () => $isAdmin ? null : RegionDealer::where('user_id', auth()->id())->active()->value('id'))
                        ->helperText('Bu personel hangi bayilik atamanız altında çalışacak (birden fazla bölgeniz varsa seçin).'),

                    Forms\Components\Select::make('department')
                        ->label('Departman')
                        ->options([
                            'galeri' => 'Galeri (Vasıta)',
                            'emlak'  => 'Emlak (Gayrimenkul)',
                            'hepsi'  => 'Hepsi',
                        ])
                        ->required()
                        ->native(false)
                        ->helperText('Personel sadece bu departmana ait kategorilerdeki talep/teklifleri görür ve onaylayabilir. Muhasebe (gelir payı) hiçbir personele açık değildir — sadece bayi sahibi görür.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->helperText('Kapatılırsa bu personel anında hiçbir talep/teklif göremez, /bayilik girişi de reddedilir.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Personel')
                    ->description(fn (DealerStaff $r) => $r->user?->phone)
                    ->searchable(),

                Tables\Columns\TextColumn::make('regionDealer.il')
                    ->label('Bölge')
                    ->formatStateUsing(fn ($state, DealerStaff $r) => $r->regionDealer?->isIlce()
                        ? "{$r->regionDealer->il} / {$r->regionDealer->ilce}"
                        : $state)
                    ->description(fn (DealerStaff $r) => $r->regionDealer?->user?->name),

                Tables\Columns\BadgeColumn::make('department')
                    ->label('Departman')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'galeri' => 'Galeri (Vasıta)',
                        'emlak'  => 'Emlak (Gayrimenkul)',
                        'hepsi'  => 'Hepsi',
                        default  => $state,
                    })
                    ->colors(['primary' => 'galeri', 'success' => 'emlak', 'gray' => 'hepsi']),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Eklendi')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->label('Departman')
                    ->options(['galeri' => 'Galeri', 'emlak' => 'Emlak', 'hepsi' => 'Hepsi']),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif mi'),
            ])
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
            'index'  => Pages\ListDealerStaff::route('/'),
            'create' => Pages\CreateDealerStaff::route('/create'),
            'edit'   => Pages\EditDealerStaff::route('/{record}/edit'),
        ];
    }
}
