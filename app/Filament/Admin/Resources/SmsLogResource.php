<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SmsLogResource\Pages;
use App\Models\SmsLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SmsLogResource extends Resource
{
    protected static ?string $model            = SmsLog::class;
    protected static ?string $navigationLabel  = 'SMS Logları';
    protected static ?string $navigationIcon   = 'heroicon-o-chat-bubble-left';
    protected static ?string $navigationGroup  = 'Sistem';
    protected static ?string $modelLabel       = 'SMS Log';
    protected static ?string $pluralModelLabel = 'SMS Logları';
    protected static ?int    $navigationSort   = 1;

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
                    ->formatStateUsing(fn($state) => match($state) {
                        'register'       => 'Kayıt',
                        'login'          => 'Giriş',
                        'password_reset' => 'Şifre Sıfırlama',
                        default          => $state,
                    })
                    ->colors([
                        'primary' => 'register',
                        'success' => 'login',
                        'warning' => 'password_reset',
                    ]),
                Tables\Columns\TextColumn::make('attempt_count')
                    ->label('Yanlış Deneme')->alignCenter(),
                Tables\Columns\IconColumn::make('used_at')
                    ->label('Kullanıldı')
                    ->boolean()
                    ->getStateUsing(fn($record) => $record->used_at !== null)
                    ->trueColor('success')->falseColor('warning'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Son Kullanma')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Gönderildi')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('purpose')
                    ->label('Amaç')
                    ->options([
                        'register'       => 'Kayıt',
                        'login'          => 'Giriş',
                        'password_reset' => 'Şifre Sıfırlama',
                    ]),
            ])
            ->poll('10s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsLogs::route('/'),
        ];
    }

    public static function canCreate(): bool        { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }
}