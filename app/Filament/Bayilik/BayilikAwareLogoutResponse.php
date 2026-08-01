<?php

namespace App\Filament\Bayilik;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Filament'in varsayılan LogoutResponse'u (bkz.
 * vendor/filament/filament/src/Http/Responses/Auth/LogoutResponse.php)
 * çıkış sonrası Filament::hasLogin() ? Filament::getLoginUrl() :
 * Filament::getUrl() adresine yönlendiriyor — 'bayilik' panelinde
 * ->login() TANIMLI OLMADIĞI için (bkz. BayilikPanelProvider — dealer_staff
 * hesaplarının şifresi yok, Filament'in e-posta/şifre formu onlara işlemiyor)
 * bu, kullanıcıyı çıkış yaptığı panele GERİ göndermeye çalışıp anında
 * auth middleware'e takılan bir döngüye sokuyordu.
 *
 * Bu binding SADECE 'bayilik' panelinde farklı davranıyor — 'admin'
 * panelinde Filament'in orijinal davranışı (login sayfasına dön) aynen
 * korunuyor. bkz. BayilikPanelProvider::register().
 */
class BayilikAwareLogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::getCurrentPanel()?->getId() === 'bayilik') {
            return redirect()->to('/bayilik');
        }

        return redirect()->to(
            Filament::hasLogin() ? Filament::getLoginUrl() : Filament::getUrl()
        );
    }
}
