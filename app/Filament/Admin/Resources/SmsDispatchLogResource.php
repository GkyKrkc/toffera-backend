<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SmsDispatchLogResource\Pages;
use App\Models\SmsDispatchLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Eski SmsLogResource'un (OTP kod deposu) yerine geçti.
 *
 * Neden OTP kodu artık burada gösterilmiyor: kodlar Redis'te TTL ile
 * yaşıyor, hiç DB'ye yazılmıyor (bkz. OtpService) — hem güvenlik hem
 * teknik olarak gösterilecek bir şey yok. Bu Resource, kodun kendisi
 * yerine "gönderim denetimini" (kime, ne zaman, hangi sağlayıcıyla,
 * hangi durumda, ne maliyetle) gösterir.
 */
class SmsDispatchLogResource extends Resource
{
    protected static ?string $model            = SmsDispatchLog::class;
    protected static ?string $navigationLabel  = 'SMS Gönderim Logları';
    protected static ?string $navigationIcon   = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup  = 'Sistem';
    protected static ?string $modelLabel       = 'SMS Gönderim Logu';
    protected static ?string $pluralModelLabel = 'SMS Gönderim Logları';
    protected static ?int    $navigationSort   = 1;

    /** Bayilik sistemi: SMS gönderim logları sadece admin görür. */
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
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')->searchable(),
                Tables\Columns\BadgeColumn::make('purpose')
                    ->label('Amaç')
                    ->formatStateUsing(fn($state) => str_starts_with($state, 'otp_')
                        ? match ($state) {
                            'otp_register'       => 'OTP · Kayıt',
                            'otp_login'          => 'OTP · Giriş',
                            'otp_password_reset' => 'OTP · Şifre Sıfırlama',
                            default               => 'OTP · ' . str_replace('otp_', '', $state),
                        }
                        : $state) // bildirim tipleri (AgentApproved, DemandMatched vb.) olduğu gibi gösterilir
                    ->color(fn($state) => str_starts_with($state, 'otp_') ? 'info' : 'primary')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('provider')
                    ->label('Sağlayıcı')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'log'      => 'Log (test)',
                        'netgsm'   => 'Netgsm',
                        'ileti365' => 'İleti365',
                        default    => $state,
                    })
                    ->color(fn($state) => $state === 'log' ? 'gray' : 'success'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Durum')
                    ->colors([
                        'success' => 'sent',
                        'danger'  => 'failed',
                        'gray'    => 'stub_logged',
                        'warning' => 'queued',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'sent'        => 'Gönderildi',
                        'failed'      => 'Başarısız',
                        'stub_logged' => 'Loglandı (test)',
                        'queued'      => 'Kuyrukta',
                        default       => $state,
                    }),
                Tables\Columns\TextColumn::make('message')
                    ->label('Mesaj')
                    ->limit(40)
                    ->tooltip(fn($record) => $record->message)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cost')
                    ->label('Maliyet')
                    ->money('TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Gönderildi')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'sent'        => 'Gönderildi',
                        'failed'      => 'Başarısız',
                        'stub_logged' => 'Loglandı (test)',
                        'queued'      => 'Kuyrukta',
                    ]),
                Tables\Filters\SelectFilter::make('provider')
                    ->label('Sağlayıcı')
                    ->options([
                        'log'      => 'Log (test)',
                        'netgsm'   => 'Netgsm',
                        'ileti365' => 'İleti365',
                    ]),
            ])
            ->poll('10s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsDispatchLogs::route('/'),
        ];
    }

    public static function canCreate(): bool        { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }
}
