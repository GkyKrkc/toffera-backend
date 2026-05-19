<?php

namespace App\Services;

use App\Models\User;
use App\Services\EmailService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class UserStatusService
{
    public function __construct(
        private EmailService $email,
        private SmsService   $sms,
    ) {}

    // ─────────────────────────────────────────────────────────
    // Uzman başvurusunu onayla
    // pending → active
    // ─────────────────────────────────────────────────────────
    public function approveAgent(User $user): void
    {
        $this->ensureRole($user, 'agent');
        $this->ensureStatus($user, 'pending');

        $user->update([
            'status'     => 'active',
            'admin_note' => null,
        ]);

        // SMS bildirimi
        $this->sms->sendOtp($user->phone, 'login'); // TODO: Bildirim SMS servisi ayrılacak
        Log::info("Agent onaylandı: {$user->id} — {$user->phone}");

        // Mail bildirimi (email varsa)
        if ($user->email) {
            $this->email->sendAgentApproved($user);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Uzman başvurusunu reddet
    // pending → rejected
    // ─────────────────────────────────────────────────────────
    public function rejectAgent(User $user, string $reason): void
    {
        $this->ensureRole($user, 'agent');

        $user->update([
            'status'     => 'rejected',
            'admin_note' => $reason,
        ]);

        Log::info("Agent reddedildi: {$user->id} — Sebep: {$reason}");

        if ($user->email) {
            $this->email->sendAgentRejected($user, $reason);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Reddedilen uzmanı tekrar pending'e al (yeniden başvuru)
    // rejected → pending
    // ─────────────────────────────────────────────────────────
    public function resubmitAgent(User $user): void
    {
        $this->ensureRole($user, 'agent');
        $this->ensureStatus($user, 'rejected');

        $user->update([
            'status'     => 'pending',
            'admin_note' => null,
        ]);

        Log::info("Agent yeniden başvurdu: {$user->id}");
    }

    // ─────────────────────────────────────────────────────────
    // Kullanıcıyı banla
    // ─────────────────────────────────────────────────────────
    public function ban(User $user, string $reason): void
    {
        if ($user->hasRole('admin')) {
            throw new \Exception('Admin kullanıcılar banlanamaz.');
        }

        $user->update([
            'is_banned'  => true,
            'ban_reason' => $reason,
        ]);

        // Tüm token'larını iptal et
        $user->tokens()->delete();

        Log::warning("Kullanıcı banlandı: {$user->id} — Sebep: {$reason}");
    }

    // ─────────────────────────────────────────────────────────
    // Ban kaldır
    // ─────────────────────────────────────────────────────────
    public function unban(User $user): void
    {
        $user->update([
            'is_banned'  => false,
            'ban_reason' => null,
        ]);

        Log::info("Kullanıcı ban kaldırıldı: {$user->id}");
    }

    // ─────────────────────────────────────────────────────────
    // Müşteri → Uzman rol değişimi
    // ─────────────────────────────────────────────────────────
    public function convertToAgent(User $user, string $agentType, string $companyName): void
    {
        $this->ensureRole($user, 'buyer');

        $user->syncRoles(['agent']);
        $user->update([
            'agent_type'   => $agentType,
            'company_name' => $companyName,
            'status'       => 'pending',
        ]);

        Log::info("Kullanıcı buyer→agent dönüştürüldü: {$user->id}");
    }

    // ─────────────────────────────────────────────────────────
    // Durum geçmişi özeti
    // ─────────────────────────────────────────────────────────
    public function statusSummary(User $user): array
    {
        return [
            'status'        => $user->status,
            'is_banned'     => $user->is_banned,
            'ban_reason'    => $user->ban_reason,
            'admin_note'    => $user->admin_note,
            'phone_verified'=> $user->isPhoneVerified(),
            'roles'         => $user->getRoleNames(),
            'agent_type'    => $user->agent_type,
            'is_active'     => $user->isActive(),
            'can_make_offer'=> $user->canMakeOffer(),
        ];
    }

    // ─────────────────────────────────────────────────────────
    // Private yardımcılar
    // ─────────────────────────────────────────────────────────
    private function ensureRole(User $user, string $role): void
    {
        if (!$user->hasRole($role)) {
            throw new \Exception("Bu işlem yalnızca {$role} rolündeki kullanıcılar için geçerlidir.");
        }
    }

    private function ensureStatus(User $user, string $status): void
    {
        if ($user->status !== $status) {
            throw new \Exception("Kullanıcı durumu '{$status}' olmalıdır, şu an: '{$user->status}'.");
        }
    }
}