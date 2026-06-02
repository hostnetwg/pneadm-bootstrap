<?php

namespace App\Mail;

use App\Mail\Concerns\UsesSystemMailSettings;
use App\Models\Course;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CertificateLinkMail extends Mailable
{
    use Queueable, SerializesModels, UsesSystemMailSettings;

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

        return $this->withSystemMailSettings()
            ->subject('Link do pobrania zaświadczeń – '.config('app.name'))
            ->view('emails.certificate-link')
            ->with([
                'participantFirstName' => $participantFirstName,
                'courseTitle' => $courseTitle,
                'certificatesUrl' => $this->certificatesUrl,
            ]);
    }
}
