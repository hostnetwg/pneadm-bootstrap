<?php

namespace App\Mail;

use App\Models\ClosedTrainingCase;
use App\Models\Instructor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrainerPortalCaseSharedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClosedTrainingCase $case,
        public Instructor $instructor,
        public string $panelUrl,
        public string $senderName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nowe zapytanie o szkolenie zamknięte',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.trainer-portal-case-shared',
        );
    }
}
