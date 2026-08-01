<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tüm talep/teklif bildirimlerinin ortak atası.
 *
 * Alt sınıflar yalnızca payload() döndürür; kanal seçimi ve ortak
 * JSON yapısı (key, title, message, url, icon, meta, created_at)
 * burada standardize edilir. Böylece frontend tek bir şema okur.
 *
 * Şimdilik yalnızca "database" kanalı aktif. İleride SMS/mail/push
 * eklemek istenirse via() genişletilir (kullanıcı tercihine göre).
 */
abstract class BaseDemandNotification extends Notification
{
    use Queueable;

    /** Bildirim tipini tanımlayan kısa anahtar (frontend ikon/renk için). */
    abstract public function key(): string;

    /** Bildirime özel veri: ['title','message','url','icon','meta'=>[]] */
    abstract protected function payload($notifiable): array;

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $p = $this->payload($notifiable);

        return [
            'key'     => $this->key(),
            'title'   => $p['title']   ?? '',
            'message' => $p['message'] ?? '',
            'url'     => $p['url']     ?? null,
            'icon'    => $p['icon']    ?? 'bell',
            'meta'    => $p['meta']    ?? [],
        ];
    }
}
