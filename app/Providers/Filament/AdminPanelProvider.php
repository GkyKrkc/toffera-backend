<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Resources\AgentApplicationResource;
use App\Filament\Admin\Resources\AccountTypeGroupResource;
use App\Filament\Admin\Resources\BankAccountResource;
use App\Filament\Admin\Resources\BillableProductResource;
use App\Filament\Admin\Resources\CategoryResource;
use App\Filament\Admin\Resources\DealerApplicationResource;
use App\Filament\Admin\Resources\DealerRevenueShareResource;
use App\Filament\Admin\Resources\DealerStaffResource;
use App\Filament\Admin\Resources\DemandModerationResource;
use App\Filament\Admin\Resources\OfferModerationResource;
use App\Filament\Admin\Resources\PaymentGatewaySettingResource;
use App\Filament\Admin\Resources\PaymentResource;
use App\Filament\Admin\Pages\CompanySettingsPage;
use App\Filament\Admin\Resources\LegalDocumentResource;
use App\Filament\Admin\Resources\PortfolioDocumentResource;
use App\Filament\Admin\Resources\PortfolioItemModerationResource;
use App\Filament\Admin\Resources\QuickMessageResource;
use App\Filament\Admin\Resources\RegionDealerResource;
use App\Filament\Admin\Resources\SmsDispatchLogResource;
use App\Filament\Admin\Resources\SubscriptionResource;
use App\Filament\Admin\Resources\UserResource;
use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Http\Middleware\Authenticate;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()

            ->colors([
                'primary' => Color::Indigo,   // frontend'deki ana marka rengi
                'amber'   => Color::Amber,     // frontend'deki vurgu rengi — özel badge/uyarılarda kullanılabilir
                'gray'    => Color::Slate,     // frontend'in bg-slate-50 tonuyla tutarlı nötr palet
            ])

            ->font('Plus Jakarta Sans')

            ->brandName('Teklif Meydanı Admin')

            ->sidebarCollapsibleOnDesktop()

            ->spa() // sayfa geçişleri tam yenilenmeden, anlık olur — daha "app" hissi verir

            ->maxContentWidth('full')

            // Minimal/kompakt görünüm — build gerektirmeyen, düz CSS
            // (public/css/admin-custom.css), Filament'in fi-* hook
            // class'larını override ediyor. Cache-bust için mtime
            // sorgu parametresi ekleniyor, dosya değişince tarayıcı
            // eski sürümü göstermesin diye.
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
                AgentApplicationResource::class,
                UserResource::class,
                SmsDispatchLogResource::class,
                PortfolioDocumentResource::class,
                PortfolioItemModerationResource::class,
                OfferModerationResource::class,
                DemandModerationResource::class,
                RegionDealerResource::class,
                DealerApplicationResource::class,
                DealerRevenueShareResource::class,
                DealerStaffResource::class,
                CategoryResource::class,
                AccountTypeGroupResource::class,
                PaymentGatewaySettingResource::class,
                BillableProductResource::class,
                SubscriptionResource::class,
                PaymentResource::class,
                QuickMessageResource::class,
                LegalDocumentResource::class,
                BankAccountResource::class,
            ])

            ->widgets([
                StatsOverviewWidget::class,
            ])

            ->navigationGroups([
                NavigationGroup::make('Moderasyon')
                    ->icon('heroicon-o-shield-check'),
                NavigationGroup::make('Bayilik Sistemi')
                    ->icon('heroicon-o-map'),
                NavigationGroup::make('Kullanıcı Yönetimi')
                    ->icon('heroicon-o-users'),
                NavigationGroup::make('Ödeme & Abonelik')
                    ->icon('heroicon-o-credit-card'),
                NavigationGroup::make('Sistem')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
            ])

            ->pages([
                Pages\Dashboard::class,
                CompanySettingsPage::class,
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
