<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // API isteklerinde Sanctum cookie auth
        //$middleware->statefulApi();

        // ── Alias tanımları ───────────────────────────────────
        $middleware->alias([
            // Spatie Permission (guard: sanctum)
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,

            // Özel middleware'ler
            'user.status'    => \App\Http\Middleware\CheckUserStatus::class,
            'phone.verified' => \App\Http\Middleware\EnsurePhoneVerified::class,
            'agent.approved' => \App\Http\Middleware\EnsureAgentApproved::class,
            'auth.token'     => \App\Http\Middleware\EnsureAuthToken::class,
            'offer.limit'    => \App\Http\Middleware\CheckOfferLimit::class,
        ]);
    })
    ->withProviders([
        App\Providers\Filament\AdminPanelProvider::class,
        App\Providers\RouteServiceProvider::class,  // Rate limiting tanımları
    ])
    ->withExceptions(function (Exceptions $exceptions) {
$exceptions->report(function (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    });
        // Spatie Permission exception'larını JSON'a çevir
        $exceptions->render(function (
            \Spatie\Permission\Exceptions\UnauthorizedException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Bu işlem için yetkiniz bulunmuyor.',
                    'code'    => 'FORBIDDEN',
                ], 403);
            }
        });

        // Sanctum unauthenticated
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Bu işlem için giriş yapmanız gerekiyor.',
                    'code'    => 'UNAUTHENTICATED',
                ], 401);
            }
        });

    })->create();