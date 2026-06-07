<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\UserStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private UserStatusService   $statusService,
        private SubscriptionService $subscriptionService,
    ) {}

    // ─────────────────────────────────────────────────────────
    // Bekleyen uzman başvurularını listele
    // GET /api/admin/agents/pending
    // ─────────────────────────────────────────────────────────
    public function pendingAgents(Request $request): JsonResponse
    {
        $agents = User::agents()
            ->where('status', 'pending')
            ->with('agentDocuments')
            ->latest()
            ->paginate(20);

        return response()->json($agents);
    }

    // ─────────────────────────────────────────────────────────
    // Uzman başvurusunu onayla
    // POST /api/admin/agents/{user}/approve
    // ─────────────────────────────────────────────────────────
    public function approveAgent(User $user): JsonResponse
    {
        try {
            $this->statusService->approveAgent($user);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Başvuru onaylandı.',
            'status'  => $this->statusService->statusSummary($user->fresh()),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Uzman başvurusunu reddet
    // POST /api/admin/agents/{user}/reject
    // ─────────────────────────────────────────────────────────
    public function rejectAgent(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Red sebebi zorunludur.',
        ]);

        try {
            $this->statusService->rejectAgent($user, $request->reason);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Başvuru reddedildi.',
            'status'  => $this->statusService->statusSummary($user->fresh()),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Kullanıcıyı banla
    // POST /api/admin/users/{user}/ban
    // ─────────────────────────────────────────────────────────
    public function banUser(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Ban sebebi zorunludur.',
        ]);

        try {
            $this->statusService->ban($user, $request->reason);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Kullanıcı banlandı.']);
    }

    // ─────────────────────────────────────────────────────────
    // Ban kaldır
    // POST /api/admin/users/{user}/unban
    // ─────────────────────────────────────────────────────────
    public function unbanUser(User $user): JsonResponse
    {
        $this->statusService->unban($user);

        return response()->json(['message' => 'Ban kaldırıldı.']);
    }

    // ─────────────────────────────────────────────────────────
    // Tüm kullanıcıları listele
    // GET /api/admin/users
    // ─────────────────────────────────────────────────────────
    public function users(Request $request): JsonResponse
    {
        $query = User::with('roles')
            ->when($request->status,  fn($q) => $q->where('status', $request->status))
            ->when($request->role,    fn($q) => $q->role($request->role))
            ->when($request->banned,  fn($q) => $q->banned())
            ->when($request->search,  fn($q) => $q->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->paginate(20);

        return response()->json($query);
    }

    // ─────────────────────────────────────────────────────────
    // Kullanıcı detayı
    // GET /api/admin/users/{user}
    // ─────────────────────────────────────────────────────────
    public function showUser(User $user): JsonResponse
    {
        $user->load('agentDocuments', 'roles');

        return response()->json([
            'user'         => $user,
            'status'       => $this->statusService->statusSummary($user),
            'subscription' => $this->subscriptionService->summary($user),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Admin abonelik düzenleme (ödeme almadan direkt aktif et)
    // POST /api/admin/users/{user}/subscription
    // ─────────────────────────────────────────────────────────
    public function setSubscription(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'plan'   => 'required|in:free,basic,premium,pro',
            'months' => 'nullable|integer|min:1|max:24',
        ]);

        if ($request->plan === 'free') {
            $this->subscriptionService->downgradeToFree($user);
        } else {
            $this->subscriptionService->activate($user, $request->plan, $request->months ?? 1);
        }

        return response()->json([
            'message'      => 'Abonelik güncellendi.',
            'subscription' => $this->subscriptionService->summary($user->fresh()),
        ]);
    }
}