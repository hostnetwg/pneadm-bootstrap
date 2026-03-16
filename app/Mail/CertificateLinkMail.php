<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Participant;
use App\Models\Course;

class CertificateLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public Participant $participant;
    public Course $course;
    public string $certificatesUrl;

    public function __construct(Participant $participant, Course $course, string $certificatesUrl)
    {
        $this->participant = $participant;
        $this->course = $course;
        $this->certificatesUrl = $certificatesUrl;
    }

    public function build()
    {
        $participantFirstName = trim((string) $this->participant->first_name) ?: 'Uczestniku';
        $courseTitle = $this->course->title ?? 'szkolenie';

        return $this->subject('Link do pobrania zaświadczeń – ' . config('app.name'))
            ->view('emails.certificate-link')
            ->with([
                'participantFirstName' => $participantFirstName,
                'courseTitle' => $courseTitle,
                'certificatesUrl' => $this->certificatesUrl,
            ]);
    }
}
