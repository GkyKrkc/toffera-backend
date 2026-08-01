<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserProfileController extends Controller
{
    // SettingsPage.jsx > "Bildirim Tercihleri" sekmesindeki sabit kategori
    // listesiyle birebir eşleşmeli (bkz. NotificationType::category()).
    // 'account' kasıtlı olarak burada YOK — o kategori kilitli/her zaman
    // açık, kullanıcı tarafından kapatılamaz.
    private const NOTIFICATION_PREF_CATEGORIES = [
        'new_offer', 'offer_status', 'demand_status',
        'region_activity', 'messages', 'billing',
    ];

    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'email'        => 'sometimes|nullable|email|unique:users,email,' . $request->user()->id,
            'company_name' => 'sometimes|nullable|string|max:255',
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Profil güncellendi.',
            'data'    => $request->user()->fresh(),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', Password::min(6)],
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['message' => 'Mevcut şifre yanlış.'], 422);
        }

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'Şifre güncellendi.']);
    }

    // GET /api/user/notification-preferences — SettingsPage.jsx bunu
    // açılışta çeker. Hiç kaydedilmemiş kategoriler için varsayılan
    // (sms: true, email: true) döner.
    public function notificationPreferences(Request $request): JsonResponse
    {
        $stored = $request->user()->notification_preferences ?? [];

        $data = [];
        foreach (self::NOTIFICATION_PREF_CATEGORIES as $category) {
            $data[$category] = [
                'sms'   => (bool) ($stored[$category]['sms'] ?? true),
                'email' => (bool) ($stored[$category]['email'] ?? true),
            ];
        }

        return response()->json($data);
    }

    // PUT /api/user/notification-preferences
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            '*'       => 'sometimes|array',
            '*.sms'   => 'sometimes|boolean',
            '*.email' => 'sometimes|boolean',
        ]);

        // Sadece bilinen kategoriler kaydedilir — istemciden gelebilecek
        // tanınmayan/rastgele key'ler (ör. kilitli 'account') sessizce yok sayılır.
        $clean = [];
        foreach (self::NOTIFICATION_PREF_CATEGORIES as $category) {
            if (isset($validated[$category])) {
                $clean[$category] = [
                    'sms'   => (bool) ($validated[$category]['sms'] ?? true),
                    'email' => (bool) ($validated[$category]['email'] ?? true),
                ];
            }
        }

        $request->user()->update(['notification_preferences' => $clean]);

        return response()->json(['message' => 'Bildirim tercihleri kaydedildi.']);
    }
}
