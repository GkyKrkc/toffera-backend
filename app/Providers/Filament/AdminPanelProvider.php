<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Resources\AgentApplicationResource;
use App\Filament\Admin\Resources\SmsLogResource;
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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
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
                'primary' => Color::Blue,
            ])

            ->brandName('TOFFERA Admin')

            ->resources([
                AgentApplicationResource::class,
                UserResource::class,
                SmsLogResource::class,
            ])

            ->widgets([
                StatsOverviewWidget::class,
            ])

            ->navigationGroups([
                NavigationGroup::make('Kullanıcı Yönetimi'),
                NavigationGroup::make('Sistem')
                    ->collapsed(),
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