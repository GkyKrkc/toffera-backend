<?php

namespace App\Filament\Admin\Pages;

use App\Models\CompanySetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Kurumsal bilgiler — tek satırlık (singleton) ayar sayfası. Klasik bir
 * Resource DEĞİL çünkü liste/create/delete yok, tek kayıt var (bkz.
 * CompanySetting::current()). Buradaki değerler yasal metinlerde
 * {sirket_unvani} gibi merge tag'ler olarak kullanılıyor (bkz.
 * LegalDocument::renderedBody()) — burayı güncellemek tüm metinleri
 * otomatik günceller, metinlerin kendisine dokunmaya gerek kalmaz.
 */
class CompanySettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Kurumsal Bilgiler';
    protected static ?string $navigationGroup = 'Sistem';
    protected static ?string $title           = 'Kurumsal Bilgiler';
    protected static string $view             = 'filament.admin.pages.company-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(CompanySetting::current()->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Kurumsal Bilgiler')
                    ->description('Bu alanlar, yasal metinlerde (Kullanıcı Sözleşmesi, KVKK Aydınlatma Metni vb.) otomatik yer tutucu olarak kullanılır — burayı güncellemek, tüm metinlerdeki şirket bilgilerini de otomatik günceller.')
                    ->schema([
                        Forms\Components\TextInput::make('unvan')
                            ->label('Ticari Unvan')
                            ->placeholder('Örn: Toffera Bilişim ve Ticaret A.Ş.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('adres')
                            ->label('Adres')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('telefon')
                            ->label('Telefon')
                            ->tel(),

                        Forms\Components\TextInput::make('email')
                            ->label('E-posta')
                            ->email(),

                        Forms\Components\TextInput::make('faks')
                            ->label('Faks'),

                        Forms\Components\TextInput::make('kep_adresi')
                            ->label('KEP Adresi'),

                        Forms\Components\TextInput::make('mersis_no')
                            ->label('MERSİS No'),

                        Forms\Components\TextInput::make('vergi_dairesi')
                            ->label('Vergi Dairesi'),

                        Forms\Components\TextInput::make('vergi_no')
                            ->label('Vergi No'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        CompanySetting::current()->update($data);

        Notification::make()
            ->title('Kurumsal bilgiler kaydedildi.')
            ->success()
            ->send();
    }
}
