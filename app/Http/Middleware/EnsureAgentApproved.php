<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Kimlik doğrulaması gerekli.'], 401);
        }

        // Sadece agent rolü için kontrol yap
        if (!$user->hasRole('agent')) {
            return $next($request);
        }

        // Yasaklı mı?
        if ($user->is_banned) {
            return response()->json([
                'message' => 'Hesabınız askıya alınmıştır.',
                'code'    => 'ACCOUNT_BANNED',
            ], 403);
        }

        // Onay bekliyor mu?
        if ($user->status === 'pending') {
            return response()->json([
                'message' => 'Hesabınız henüz onaylanmamış. Onay sürecinin tamamlanmasını bekleyin.',
                'code'    => 'AGENT_PENDING',
            ], 403);
        }

        // Reddedilmiş mi?
        if ($user->status === 'rejected') {
            return response()->json([
                'message' => 'Başvurunuz reddedilmiştir. Daha fazla bilgi için yönetici ile iletişime geçin.',
                'code'    => 'AGENT_REJECTED',
            ], 403);
        }

        // Aktif değilse
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Hesabınız aktif değil.',
                'code'    => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        return $next($request);
    }
}
