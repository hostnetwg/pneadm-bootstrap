<?php

namespace App\Mail;

use App\Models\PneduUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrainerPortalAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PneduUser $pneduUser,
        public string $actionUrl,
        public bool $createdAccount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Panel trenera na pnedu.pl',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.trainer-portal-access',
        );
    }
}
