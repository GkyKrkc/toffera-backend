<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationType;
use App\Events\NewMessage as NewMessageEvent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Notifications\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// ─────────────────────────────────────────────────────────────
// Mesajlaşma — bir teklif (offer) üzerinden alıcı (talep sahibi) ile
// teklifi veren uzman arasında.
//
// ÖNEMLİ KURAL: konuşmayı SADECE alıcı (demand sahibi) başlatabilir
// (bkz. start()). Uzman tarafı hiçbir zaman yeni bir konuşma açamaz,
// sadece kendisine açılmış olan konuşmalara mesaj yazabilir. Bu yüzden
// bu controller'daki route'lar rol-özel (buyer/agent) prefix'lerin
// DIŞINDA, genel kimlik doğrulamalı grupta tanımlı — her metod içeride
// "bu kullanıcı bu konuşmanın tarafı mı" kontrolü yapıyor (OfferController
// @show'daki isOwner/isAgent deseniyle aynı).
// ─────────────────────────────────────────────────────────────
class ConversationController extends Controller
{
    // GET /api/conversations — giriş yapan kullanıcının (alıcı ya da
    // uzman tarafı olduğu) tüm konuşmaları, son mesaja göre en yeniden
    // eskiye. Header'daki mesaj dropdown'ı bunu kullanır.
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::with([
            'buyer:id,name,company_name',
            'agent:id,name,company_name',
            'demand:id,title',
            'messages' => fn ($q) => $q->latest('created_at')->limit(1),
        ])
            ->where(fn ($q) => $q->where('buyer_id', $user->id)->orWhere('agent_id', $user->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Conversation $c) use ($user) {
                $isBuyer = $c->buyer_id === $user->id;
                $other = $isBuyer ? $c->agent : $c->buyer;

                return [
                    'id'             => $c->id,
                    'demand_id'      => $c->demand_id,
                    'offer_id'       => $c->offer_id,
                    'demand_title'   => $c->demand?->title,
                    'other_party'    => [
                        'id'   => $other?->id,
                        'name' => $other?->company_name ?: $other?->name,
                    ],
                    'last_message'   => $c->messages->first()?->body,
                    'last_message_at' => $c->last_message_at,
                    'unread_count'   => $c->messages()
                        ->where('sender_id', '!=', $user->id)
                        ->whereNull('read_at')
                        ->count(),
                    'status'         => $c->status,
                ];
            });

        return response()->json(['data' => $conversations]);
    }

    // GET /api/conversations/unread-count — header rozeti için hafif toplam.
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = Message::whereHas('conversation', fn ($q) => $q
                ->where('buyer_id', $user->id)
                ->orWhere('agent_id', $user->id))
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    // POST /api/conversations/offers/{offer} — "Görüşme Başlat".
    // SADECE talebin sahibi (alıcı) çağırabilir. Bu teklif için zaten bir
    // konuşma varsa onu döndürür, yenisini açmaz (offer_id unique).
    public function start(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();
        $offer->loadMissing('demand');

        if (!$offer->demand->isOwnedBy($user)) {
            return response()->json([
                'message' => 'Görüşmeyi yalnızca talep sahibi başlatabilir.',
            ], 403);
        }

        $conversation = Conversation::firstOrCreate(
            ['offer_id' => $offer->id],
            [
                'demand_id' => $offer->demand_id,
                'buyer_id'  => $offer->demand->user_id,
                'agent_id'  => $offer->user_id,
                'status'    => 'active',
            ]
        );

        return response()->json(['data' => $conversation], 201);
    }

    // GET /api/conversations/{conversation}/messages
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        if (!$conversation->isParticipant($user->id)) {
            return response()->json(['message' => 'Bu görüşmeye erişiminiz yok.'], 403);
        }

        $messages = $conversation->messages()
            ->with('sender:id,name,company_name')
            ->paginate(50)
            ->toArray();

        // Frontend (ConversationPanel.jsx), konuşma kapandıysa ("Önceki
        // Mesajlar") mesaj kutusunu devre dışı bırakmak için bu alanı okur.
        $messages['conversation'] = [
            'id'     => $conversation->id,
            'status' => $conversation->status,
        ];

        return response()->json($messages);
    }

    // POST /api/conversations/{conversation}/messages
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        if (!$conversation->isParticipant($user->id)) {
            return response()->json(['message' => 'Bu görüşmeye erişiminiz yok.'], 403);
        }

        if (!$conversation->isActive()) {
            return response()->json(['message' => 'Bu görüşme kapandı, mesaj gönderilemez.'], 422);
        }

        $validated = $request->validate([
            'body'             => 'required|string|max:2000',
            'quick_message_id' => 'nullable|exists:quick_messages,id',
        ]);

        $message = $conversation->messages()->create([
            'sender_id'        => $user->id,
            'body'             => $validated['body'],
            'quick_message_id' => $validated['quick_message_id'] ?? null,
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);
        $message->load('sender:id,name,company_name');

        $recipientId = $conversation->otherParty($user->id);
        if ($recipientId) {
            $recipient = \App\Models\User::find($recipientId);
            $senderName = $user->company_name ?: $user->name;

            $recipient?->notify(new AppNotification(NotificationType::NEW_MESSAGE, [
                'sender_name'      => $senderName,
                'message_preview'  => Str::limit($message->body, 60),
                'conversation_id'  => $conversation->id,
                'demand_id'        => $conversation->demand_id,
                'action_url'       => "/market/{$conversation->demand_id}/offers/{$conversation->offer_id}",
            ]));

            // Anlık teslimat — açık sohbet paneli varsa mesajı beklemeden ekler
            // (bkz. Events/NewMessage.php + frontend useMessages.js .new.message).
            event(new NewMessageEvent($message, $recipientId));
        }

        return response()->json(['data' => $message], 201);
    }

    // POST /api/conversations/{conversation}/read — diğer tarafın mesajlarını okundu işaretle.
    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        if (!$conversation->isParticipant($user->id)) {
            return response()->json(['message' => 'Bu görüşmeye erişiminiz yok.'], 403);
        }

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Okundu olarak işaretlendi.']);
    }
}
