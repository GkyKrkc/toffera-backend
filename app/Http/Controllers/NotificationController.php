<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Kullanıcının bildirimlerini yöneten API.
 * Laravel'in Notifiable trait'i sayesinde $request->user()->notifications
 * doğrudan kullanılabilir.
 *
 * Route'lar (auth:sanctum altında):
 *   GET    /notifications                 → sayfalı liste
 *   GET    /notifications/unread-count     → okunmamış sayısı (badge için)
 *   POST   /notifications/{id}/read        → tek bildirimi okundu işaretle
 *   POST   /notifications/read-all         → hepsini okundu işaretle
 *   DELETE /notifications/{id}             → bildirimi sil
 */
class NotificationController extends Controller
{
    /** Sayfalı bildirim listesi (varsayılan 20/sayfa). */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $request->boolean('unread')
            ? $user->unreadNotifications()
            : $user->notifications();

        $notifications = $query->paginate(
            perPage: min((int) $request->input('per_page', 20), 50)
        );

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'total'        => $notifications->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /** Badge için okunmamış sayısı. */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** Tek bir bildirimi okundu işaretle. */
    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['status' => 'ok']);
    }

    /** Tüm okunmamışları okundu işaretle. */
    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['status' => 'ok']);
    }

    /** Bir bildirimi sil. */
    public function destroy(Request $request, string $id)
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return response()->json(['status' => 'ok']);
    }
}
