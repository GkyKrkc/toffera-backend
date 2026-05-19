<?php

namespace App\Mail;

use App\Models\EmailVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationUrl;
    public string $userName;

    public function __construct(EmailVerification $verification)
    {
        $this->userName        = $verification->user->name;
        $this->verificationUrl = config('app.frontend_url')
            . '/email/verify/'
            . $verification->token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'E-posta Adresinizi Doğrulayın — TOFFERA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify',
        );
    }
}