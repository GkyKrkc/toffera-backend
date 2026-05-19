<?php

namespace App\Services;

use App\Mail\AgentApprovedMail;
use App\Mail\AgentRejectedMail;
use App\Mail\VerificationMail;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    // ── E-posta doğrulama ────────────────────────────────────

    public function sendVerification(User $user): void
    {
        if (!$user->email) return;

        $verification = EmailVerification::createForUser($user);

        $this->send($user->email, new VerificationMail($verification));
    }

    public function verifyEmail(string $token): User
    {
        $verification = EmailVerification::where('token', $token)
            ->whereNull('verified_at')
            ->with('user')
            ->first();

        if (!$verification) {
            throw new \Exception('Geçersiz veya süresi dolmuş doğrulama bağlantısı.');
        }

        if ($verification->isExpired()) {
            throw new \Exception('Doğrulama bağlantısının süresi dolmuş. Yeni link isteyin.');
        }

        $verification->markAsVerified();
        $verification->user->update(['email_verified_at' => now()]);

        return $verification->user;
    }

    // ── Uzman bildirimleri ────────────────────────────────────

    public function sendAgentApproved(User $user): void
    {
        if (!$user->email) return;

        $this->send($user->email, new AgentApprovedMail($user));
    }

    public function sendAgentRejected(User $user, string $reason): void
    {
        if (!$user->email) return;

        $this->send($user->email, new AgentRejectedMail($user, $reason));
    }

    // ── Private: güvenli gönderim ─────────────────────────────

    private function send(string $to, \Illuminate\Mail\Mailable $mailable): void
    {
        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            // Mail gönderilemese de uygulama çökmemeli
            Log::error('Mail gönderilemedi', [
                'to'    => $to,
                'mail'  => get_class($mailable),
                'error' => $e->getMessage(),
            ]);
        }
    }
}