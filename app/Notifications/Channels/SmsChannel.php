<?php

namespace App\Notifications\Channels;

use App\Models\SmsDispatchLog;
use App\Services\SmsGatewayService;
use Illuminate\Notifications\Notification;

/**
 * Laravel'de "sms" diye hazır bir kanal yok, "database"/"mail"/"broadcast"
 * gibi kendimiz tanımlıyoruz. AppServiceProvider::boot() içinde şu satırla
 * kayıt edilecek:
 *
 *   Notification::extend('sms', fn () => app(SmsChannel::class));
 *
 * Gönderim SmsGatewayService üzerinden — provider (netgsm/ileti365/log)
 * config/sms.php'de tek yerde seçiliyor, sağlayıcı değişince burası
 * hiç değişmez.
 */
class SmsChannel
{
    public function __construct(private SmsGatewayService $gateway)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }

        $phone = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms()
            : ($notifiable->phone ?? null);

        if (!$phone) {
            return;
        }

        $message = $notification->toSms($notifiable);
        $result  = $this->gateway->send($phone, $message);

        SmsDispatchLog::create([
            'phone'               => $phone,
            'purpose'             => class_basename($notification),
            'message'             => $message,
            'provider'            => $result['provider'],
            'provider_message_id' => $result['provider_message_id'],
            'status'              => $result['status'],
            'cost'                => 0, // platform gideri — kullanıcı kontöründen düşülmez
        ]);
    }
}
