<?php

use Illuminate\Support\Facades\Broadcast;

// Kullanıcı sadece kendi private kanalını dinleyebilir
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// NOT: eskiden burada genel bir 'agents' kanalı vardı (agent_type'a göre
// yetkilendiriyordu) — artık kullanılmıyor, tüm bildirimler kullanıcıya
// özel user.{id} kanalından gidiyor (bkz. App\Events\NewDemand). Ayrıca
// agent_type yeni/özel hesap gruplarında (Plaza, Rent A Car vb.) hep null
// kaldığı için o kullanıcılar bu kanala zaten hiç giremiyordu — ölü VE
// yanlış kod olduğu için tamamen kaldırıldı.
