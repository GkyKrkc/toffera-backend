<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Resources\DealerRevenueShareResource;
use App\Filament\Admin\Resources\DealerStaffResource;
use App\Filament\Admin\Resources\DemandModerationResource;
use App\Filament\Admin\Resources\OfferModerationResource;
use App\Filament\Bayilik\BayilikAwareLogoutResponse;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Bayi sahibi / departman personeli için AYRI panel — genel merkez
 * (AdminPanelProvider, id 'admin') ile aynı 'web' guard/session'ı
 * paylaşır (bkz. BayilikAuthController::verifyOtp — Auth::guard('web')
 * ->login() sonrası buraya redirect eder) ama TAMAMEN farklı bir
 * kaynak listesine sahip: dealer/dealer_staff artık /admin'e HİÇ
 * giremiyor (bkz. User::canAccessPanel), sadece buraya.
 *
 * NEDEN path 'admin/bayilik' (ayrı bir domain/prefix DEĞİL): nginx
 * sadece belirli path'leri (api, admin, livewire, app, phpmyadmin,
 * *.php) Laravel'e yönlendiriyor. 'admin' ile başlayan HER path zaten
 * proxy'leniyor — 'admin/bayilik' bu kurala uyduğu için YENİ BİR
 * SUNUCU AYARI GEREKMİYOR. Farklı bir resource seti + farklı erişim
 * kontrolü + farklı marka rengi = "ayrı panel" isteğini karşılıyor,
 * URL'in /admin altında iç içe olması sadece deploy kolaylığı içindir.
 *
 * ÖNEMLİ SINIRLAMA: Filament TÜM panelleri her request'te (route/
 * component listesi oluşturmak için) boot ediyor — yani bu paneldeki
 * bir resource'ta sınıf bulunamadı/syntax hatası gibi bir sorun olursa
 * bu YİNE /admin panelini de çökertir (ayrı panel olması bunu izole
 * ETMEZ). Bu ayrım sadece ERİŞİM ve GÖRÜNÜRLÜK içindir, deploy/autoload
 * hatalarına karşı bir izolasyon sağlamaz — o yüzden her deploy sonrası
 * `composer dump-autoload` hâlâ şart.
 */
class BayilikPanelProvider extends PanelProvider
{
    /**
     * Filament'in varsayılan çıkış-sonrası yönlendirmesi (LogoutResponse)
     * login sayfası olmayan panellerde panelin KENDİSİNE geri dönüyor —
     * bu da anında auth middleware'e takılıp döngüye giriyordu. Bu binding
     * SADECE 'bayilik' panelinde davranışı değiştirip https://teklifmeydani.com/bayilik
     * adresine yönlendiriyor, 'admin' paneli etkilenmiyor — bkz.
     * BayilikAwareLogoutResponse.
     */
    public function register(): void
    {
        parent::register();

        $this->app->bind(LogoutResponse::class, BayilikAwareLogoutResponse::class);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('bayilik')
            ->path('admin/bayilik')
            // Bilerek ->login() YOK: dealer_staff hesaplarının şifresi yok,
            // Filament'in varsayılan e-posta/şifre giriş formu onlar için
            // hiç kullanılamaz. Tek giriş kapısı https://teklifmeydani.com/bayilik
            // (SMS OTP) — session zaten oradan kurulup buraya redirect ediliyor.
            // Session yoksa/bittiyse Authenticate middleware erişimi reddeder,
            // kullanıcı tekrar /bayilik'e dönüp OTP ile girer.

            ->colors([
                'primary' => Color::Amber,    // ana admin panelden (Indigo) BİLEREK farklı — karışmasınlar
                'gray'    => Color::Slate,
            ])

            ->font('Plus Jakarta Sans')

            ->brandName('Teklif Meydanı Bayilik')

            ->sidebarCollapsibleOnDesktop()

            ->spa()

            ->maxContentWidth('full')

            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => new HtmlString(
                    '<link rel="stylesheet" href="' .
                    asset('css/admin-custom.css') .
                    '?v=' . (file_exists(public_path('css/admin-custom.css')) ? filemtime(public_path('css/admin-custom.css')) : '1') .
                    '">'
                )
            )

            ->resources([
                DemandModerationResource::class,
                OfferModerationResource::class,
                DealerStaffResource::class,
                DealerRevenueShareResource::class,
            ])

            ->navigationGroups([
                NavigationGroup::make('Moderasyon')
                    ->icon('heroicon-o-shield-check'),
                NavigationGroup::make('Bayilik Sistemi')
                    ->icon('heroicon-o-map'),
            ])

            ->pages([
                Pages\Dashboard::class,
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])

            ->authMiddleware([
                Authenticate::class,
            ])

            ->authGuard('web');
    }
}
