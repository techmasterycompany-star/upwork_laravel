<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email - Job Board Platform',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            with: ['code' => $this->code],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}