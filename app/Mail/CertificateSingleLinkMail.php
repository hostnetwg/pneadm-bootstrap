<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CertificateSingleLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Participant $participant,
        public Course $course,
        public string $certificateUrl,
        public ?string $trainerName = null
    ) {}

    public function build()
    {
        $participantFirstName = trim((string) $this->participant->first_name) ?: 'Uczestniku';
        $courseTitle = $this->course->title ?? 'szkolenie';
        $trainer = $this->trainerName;

        return $this->subject('Zaświadczenie – ' . $courseTitle . ' – ' . config('app.name'))
            ->view('emails.certificate-single-link')
            ->with([
                'participantFirstName' => $participantFirstName,
                'courseTitle' => $courseTitle,
                'trainerName' => $trainer,
                'certificateUrl' => $this->certificateUrl,
            ]);
    }
}

