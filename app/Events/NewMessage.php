<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Anlık teslimat — açık sohbet paneli varsa mesajı DB'ye tekrar gitmeden
// listeye ekleyebilsin diye. Kalıcı bildirim (bell/rozet) ayrı olarak
// AppNotification::NEW_MESSAGE ile gönderiliyor (bkz. ConversationController
// @sendMessage) — bu event SADECE canlı UI güncellemesi içindir, veritabanı
// tek gerçek kaynak (aynı mimari: useNotifications.js'deki yorum).
class NewMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message, public int $recipientId) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->recipientId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id'               => $this->message->id,
            'conversation_id'  => $this->message->conversation_id,
            'sender_id'        => $this->message->sender_id,
            'sender_name'      => $this->message->sender->company_name ?? $this->message->sender->name,
            'body'             => $this->message->body,
            'created_at'       => $this->message->created_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.message';
    }
}
