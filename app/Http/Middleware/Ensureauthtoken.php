<?php

namespace App\Http\Middleware;

use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthToken
{
    public function __construct(private TokenService $tokenService) {}

    /**
     * Kayıt akışı token'ı (register-flow) ile normal
     * endpoint'lere erişimi engeller.
     *
     * Örnek: Kayıt yarım kalmış kullanıcı /api/me endpoint'ini çağırmamalı.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($this->tokenService->isRegisterFlowToken($user)) {
            return response()->json([
                'message' => 'Kayıt işleminizi tamamlamanız gerekiyor.',
                'code'    => 'REGISTRATION_INCOMPLETE',
            ], 403);
        }

        return $next($request);
    }
}