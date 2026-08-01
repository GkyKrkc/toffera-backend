<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SubscriptionResource\Pages;
use App\Models\BillableProduct;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Kullanıcıların GERÇEK abonelik kayıtlarının listesi (kim hangi paketi ne
 * zaman satın aldı / admin tarafından tanımlandı). BillableProductResource
 * SATILAN ÜRÜN kataloğunu yönetir, bu kaynak ise o ürünlerin kullanıcılara
 * uygulanmış somut örneklerini (Subscription satırlarını) gösterir.
 *
 * Yeni kayıt normalde PayTR ödemesi başarılı olunca otomatik açılır
 * (bkz. PaymentService::grantEntitlement()); buradaki "Manuel Abonelik
 * Tanımla" header action'ı sadece admin'in ödeme almadan istisnai olarak
 * bir kullanıcıya paket tanımlaması gerektiğinde kullanılır — ham Eloquent
 * create yerine SubscriptionService::grantByAdmin() çağırır ki önceki aktif
 * abonelik doğru şekilde iptal edilsin ve tarihler doğru hesaplansın.
 */
class SubscriptionResource extends Resource
{
    protected static ?string $model            = Subscription::class;
    protected static ?string $navigationLabel   = 'Kullanıcı Abonelikleri';
    protected static ?string $navigationIcon    = 'heroicon-o-identification';
    protected static ?string $navigationGroup   = 'Ödeme & Abonelik';
    protected static ?string $modelLabel        = 'Abonelik';
    protected static ?string $pluralModelLabel  = 'Kullanıcı Abonelikleri';
    protected static ?int    $navigationSort    = 3;

    public static function canCreate(): bool
    {
        // Standart Filament create formu yerine header'daki özel
        // "Manuel Abonelik Tanımla" action'ı kullanılıyor (aşağıda).
        return false;
    }

    public static function canEdit($record): bool { return false; }

    /** Bayilik sistemi: abonelik kayıtları sadece admin görür. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Kullanıcı')
                    ->searchable()
                    ->description(fn (Subscription $record) => $record->user?->phone),

                Tables\Columns\TextColumn::make('billableProduct.name')
                    ->label('Paket'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Durum')
                    ->colors([
                        'success' => 'active',
                        'gray'    => 'expired',
                        'danger'  => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active'    => 'Aktif',
                        'expired'   => 'Süresi Doldu',
                        'cancelled' => 'İptal Edildi',
                        default     => $state,
                    }),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Başlangıç')->dateTime('d.m.Y')->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Bitiş')->dateTime('d.m.Y')->sortable(),

                Tables\Columns\TextColumn::make('offers_used_this_period')
                    ->label('Kullanılan Teklif'),

                Tables\Columns\IconColumn::make('auto_renew')
                    ->label('Otomatik Yenileme')->boolean(),

                Tables\Columns\TextColumn::make('payment_id')
                    ->label('Ödeme #')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : 'Admin tarafından'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Durum')
                    ->options(['active' => 'Aktif', 'expired' => 'Süresi Doldu', 'cancelled' => 'İptal Edildi']),
            ])
            ->headerActions([
                Tables\Actions\Action::make('grantManual')
                    ->label('Manuel Abonelik Tanımla')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Kullanıcı')
                            ->options(fn () => User::query()->limit(200)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('billable_product_id')
                            ->label('Paket')
                            ->options(fn () => BillableProduct::where('type', 'subscription')->where('is_active', true)->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $user    = User::findOrFail($data['user_id']);
                        $product = BillableProduct::findOrFail($data['billable_product_id']);

                        app(SubscriptionService::class)->grantByAdmin($user, $product);

                        Notification::make()
                            ->title("{$user->name} için {$product->name} tanımlandı.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('İptal Et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Subscription $record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalDescription('Bu abonelik hemen iptal edilecek ve süresi anında dolacak.')
                    ->action(function (Subscription $record) {
                        app(SubscriptionService::class)->cancel($record->user);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
        ];
    }
}
