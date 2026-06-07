<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AgentApplicationResource\Pages;
use App\Models\User;
use App\Services\UserStatusService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AgentApplicationResource extends Resource
{
    protected static ?string $model            = User::class;
    protected static ?string $navigationLabel  = 'Uzman Başvuruları';
    protected static ?string $navigationIcon   = 'heroicon-o-identification';
    protected static ?string $navigationGroup  = 'Kullanıcı Yönetimi';
    protected static ?string $modelLabel       = 'Başvuru';
    protected static ?string $pluralModelLabel = 'Başvurular';
    protected static ?int    $navigationSort   = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('agent');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Başvuru Sahibi')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Ad Soyad')->disabled(),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')->disabled(),
                    Forms\Components\TextInput::make('email')
                        ->label('E-posta')->disabled(),
                    Forms\Components\TextInput::make('company_name')
                        ->label('Firma / İşletme Adı')->disabled(),
                    Forms\Components\Select::make('agent_type')
                        ->label('Uzman Türü')
                        ->options([
                            'emlakci'   => 'Emlakçı',
                            'galerici'  => 'Galerici',
                            'her_ikisi' => 'Emlakçı + Galerici',
                        ])->disabled(),
                    Forms\Components\Select::make('status')
                        ->label('Durum')
                        ->options([
                            'pending'  => 'Beklemede',
                            'active'   => 'Aktif',
                            'rejected' => 'Reddedildi',
                        ])->required(),
                    Forms\Components\Textarea::make('admin_note')
                        ->label('Admin Notu / Red Sebebi')
                        ->rows(3)->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Yüklenen Belgeler')
                ->schema([
                    Forms\Components\Placeholder::make('documents')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record) return 'Kayıt seçilmedi.';
                            $docs = $record->agentDocuments;
                            if ($docs->isEmpty()) return 'Henüz belge yüklenmemiş.';
                            return $docs->map(fn($d) =>
                                "• {$d->type_label} — {$d->original_name} ({$d->file_size_human})"
                            )->implode("\n");
                        }),
                ]),
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
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Firma')->searchable()->default('-'),
                Tables\Columns\BadgeColumn::make('agent_type')
                    ->label('Tür')
                    ->formatStateUsing(fn($state) => match($state) {
                        'emlakci'   => 'Emlakçı',
                        'galerici'  => 'Galerici',
                        'her_ikisi' => 'Her ikisi',
                        default     => '-',
                    })
                    ->colors([
                        'primary' => 'emlakci',
                        'success' => 'galerici',
                        'warning' => 'her_ikisi',
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
                Tables\Columns\TextColumn::make('agentDocuments_count')
                    ->label('Belge')->counts('agentDocuments')->suffix(' dosya'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Başvuru Tarihi')->dateTime('d.m.Y H:i')->sortable(),
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
                Tables\Filters\SelectFilter::make('agent_type')
                    ->label('Uzman Türü')
                    ->options([
                        'emlakci'   => 'Emlakçı',
                        'galerici'  => 'Galerici',
                        'her_ikisi' => 'Her ikisi',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Başvuruyu Onayla')
                    ->modalDescription('Bu uzman hesabını aktif hale getirmek istediğinizden emin misiniz?')
                    ->action(function (User $record) {
                        try {
                            app(UserStatusService::class)->approveAgent($record);
                            Notification::make()->title('Başvuru onaylandı.')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn(User $record) => $record->status === 'pending'),

                Tables\Actions\Action::make('reject')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Red Sebebi')->required()->rows(3),
                    ])
                    ->action(function (User $record, array $data) {
                        try {
                            app(UserStatusService::class)->rejectAgent($record, $data['reason']);
                            Notification::make()->title('Başvuru reddedildi.')->warning()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn(User $record) => in_array($record->status, ['pending', 'active'])),

                Tables\Actions\EditAction::make()->label('Düzenle'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_approve')
                    ->label('Toplu Onayla')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $service = app(UserStatusService::class);
                        $records->each(function ($record) use ($service) {
                            try { $service->approveAgent($record); } catch (\Exception) {}
                        });
                        Notification::make()->title('Seçili başvurular onaylandı.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentApplications::route('/'),
            'edit'  => Pages\EditAgentApplication::route('/{record}/edit'),
        ];
    }

    // Yeni başvuru oluşturmayı kapat — uzmanlar API üzerinden kayıt olur
    public static function canCreate(): bool { return false; }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }
}