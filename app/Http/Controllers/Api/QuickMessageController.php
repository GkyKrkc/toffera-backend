<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuickMessage;
use Illuminate\Http\JsonResponse;

// Admin panelinden (Filament QuickMessageResource) yönetilen hazır mesaj
// önerileri — mesajlaşma panelinde tek tıkla gönderilebilen çipler.
class QuickMessageController extends Controller
{
    // GET /api/quick-messages — herhangi bir giriş yapmış kullanıcı okuyabilir.
    public function index(): JsonResponse
    {
        return response()->json(['data' => QuickMessage::active()->get()]);
    }
}
