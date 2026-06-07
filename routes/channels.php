<?php

use Illuminate\Support\Facades\Broadcast;

// Kullanıcı sadece kendi private kanalını dinleyebilir
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Agent kanalı — emlakci / galerici / her_ikisi ve aktif olanlar
Broadcast::channel('agents', function ($user) {
    return in_array($user->agent_type, ['emlakci', 'galerici', 'her_ikisi'])
        && $user->status === 'active';
});
