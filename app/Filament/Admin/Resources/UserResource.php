<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\UserStatusService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model            = User::class;
    protected static ?string $navigationLabel  = 'Tüm Kullanıcılar';
    protected static ?string $navigationIcon   = 'heroicon-o-users';
    protected static ?string $navigationGroup  = 'Kullanıcı Yönetimi';
    protected static ?string $modelLabel       = 'Kullanıcı';
    protected static ?string $pluralModelLabel = 'Kullanıcılar';
    protected static ?int    $navigationSort   = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Temel Bilgiler')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Ad Soyad')->required(),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled(fn($record) => $record !== null) // Düzenlemede pasif, oluşturmada aktif
                        ->dehydrated(fn($record) => $record === null) // Sadece oluşturmada kaydet
                        ->helperText('Telefon numarası oluşturulduktan sonra değiştirilemez.'),
                    Forms\Components\TextInput::make('email')
                        ->label('E-posta')->email(),
                    Forms\Components\TextInput::make('company_name')
                        ->label('Firma Adı'),
                    Forms\Components\Select::make('status')
                        ->label('Durum')
                        ->options([
                            'pending'  => 'Beklemede',
                            'active'   => 'Aktif',
                            'rejected' => 'Reddedildi',
                        ])->required(),
                    Forms\Components\Toggle::make('is_banned')
                        ->label('Banlandı mı?'),
                    Forms\Components\Textarea::make('ban_reason')
                        ->label('Ban Sebebi')->rows(2)->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Abonelik')
                ->schema([
                    Forms\Components\Select::make('subscription_plan')
                        ->label('Plan')
                        ->options([
                            'free'    => 'Ücretsiz',
                            'basic'   => 'Temel',
                            'premium' => 'Premium',
                            'pro'     => 'Pro',
                        ]),
                    Forms\Components\TextInput::make('offer_limit')
                        ->label('Teklif Limiti (0 = sınırsız)')->numeric()->minValue(0),
                    Forms\Components\DateTimePicker::make('subscription_ends_at')
                        ->label('Abonelik Bitiş Tarihi'),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad Soyad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')->searchable(),
                Tables\Columns\BadgeColumn::make('roles.name')
                    ->label('Rol')
                    ->formatStateUsing(fn($state) => match($state) {
                        'admin'  => 'Admin',
                        'buyer'  => 'Müşteri',
                        'agent'  => 'Uzman',
                        default  => $state,
                    })
                    ->colors([
                        'danger'  => 'admin',
                        'primary' => 'buyer',
                        'success' => 'agent',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Durum')
                    ->formatStateUsing(fn($state) => match($state) {
                        'pending'  => 'Beklemede',
                        'active'   => 'Aktif',
                        'rejected' => 'Reddedildi',
                        default    => $state,
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'active',
                        'danger'  => 'rejected',
                    ]),
                Tables\Columns\IconColumn::make('is_banned')
                    ->label('Banlı')->boolean()
                    ->trueColor('danger')->falseColor('success'),
                Tables\Columns\BadgeColumn::make('subscription_plan')
                    ->label('Plan')
                    ->colors([
                        'secondary' => 'free',
                        'primary'   => 'basic',
                        'warning'   => 'premium',
                        'success'   => 'pro',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Kayıt Tarihi')->dateTime('d.m.Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'pending'  => 'Beklemede',
                        'active'   => 'Aktif',
                        'rejected' => 'Reddedildi',
                    ]),
                Tables\Filters\SelectFilter::make('subscription_plan')
                    ->label('Plan')
                    ->options([
                        'free'    => 'Ücretsiz',
                        'basic'   => 'Temel',
                        'premium' => 'Premium',
                        'pro'     => 'Pro',
                    ]),
                Tables\Filters\TernaryFilter::make('is_banned')
                    ->label('Ban Durumu')
                    ->trueLabel('Banlılar')
                    ->falseLabel('Aktifler'),
            ])
            ->actions([
                Tables\Actions\Action::make('ban')
                    ->label('Banla')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Ban Sebebi')->required()->rows(2),
                    ])
                    ->action(function (User $record, array $data) {
                        try {
                            app(UserStatusService::class)->ban($record, $data['reason']);
                            Notification::make()->title('Kullanıcı banlandı.')->warning()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn(User $record) => !$record->is_banned && !$record->hasRole('admin')),

                Tables\Actions\Action::make('unban')
                    ->label('Ban Kaldır')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        app(UserStatusService::class)->unban($record);
                        Notification::make()->title('Ban kaldırıldı.')->success()->send();
                    })
                    ->visible(fn(User $record) => $record->is_banned),

                Tables\Actions\EditAction::make()->label('Düzenle'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}