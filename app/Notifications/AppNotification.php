<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * TEK bildirim class'ı, tüm tipleri kapsar. Kullanımı her yerde aynı:
 *
 *   $user->notify(new AppNotification(NotificationType::OFFER_ACCEPTED, [
 *       'offer_id'  => $offer->id,
 *       'demand_id' => $offer->demand_id,
 *       'action_url'=> "/market/{$offer->demand_id}/offers/{$offer->id}",
 *   ]));
 *
 * Yeni bir bildirim tipi eklemek için: sadece NotificationType enum'una
 * yeni case + template ekle, bu dosyaya HİÇ dokunma.
 */
class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected NotificationType $type,
        protected array $payload = []
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = $this->type->channels();
        $category = $this->type->category();

        // database/broadcast (uygulama içi bildirim çanı) HER ZAMAN gider.
        // Kullanıcı sadece "dışa dönük" sms/mail kanallarını, Ayarlar >
        // Bildirim Tercihleri'nden kapatabilir (bkz. User::wantsChannel()).
        return array_values(array_filter($channels, function ($channel) use ($notifiable, $category) {
            if (!in_array($channel, ['sms', 'mail'], true)) {
                return true;
            }
            if (!method_exists($notifiable, 'wantsChannel')) {
                return true;
            }
            $prefKey = $channel === 'mail' ? 'email' : $channel;
            return $notifiable->wantsChannel($category, $prefKey);
        }));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
                'type'    => $this->type->value,
                'title'   => $this->type->title(),
                'message' => $this->renderMessage(),
                'icon'    => $this->type->icon(),
                // 'url' — frontend NotificationsPage.jsx/Navbar bunu okuyor.
                // Eski 'action_url' ismi de payload'dan geldiği için altta
                // ayrıca duruyor, zararı yok, sadece 'url' asıl kullanılan.
                'url'     => $this->payload['action_url'] ?? $this->payload['url'] ?? null,
            ] + $this->payload; // ham payload'ı da sakla (frontend ihtiyaç duyabilir)
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->type->title())
            ->line($this->renderMessage())
            ->when(
                isset($this->payload['action_url']),
                fn ($mail) => $mail->action('Görüntüle', url($this->payload['action_url']))
            );
    }

    /**
     * SMS kanalı — App\Notifications\Channels\SmsChannel tarafından okunur.
     * (bkz. aşağıdaki SmsChannel sınıfı)
     */
    public function toSms(object $notifiable): string
    {
        return $this->renderMessage();
    }

    private function renderMessage(): string
    {
        $message = $this->type->template();
        foreach ($this->payload as $key => $value) {
            if (is_scalar($value)) {
                $message = str_replace('{' . $key . '}', (string) $value, $message);
            }
        }
        return $message;
    }
}
