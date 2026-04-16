<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CourseAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Participant $participant,
        public Course $course,
        public string $courseUrl,
        public bool $hasVideos,
        public bool $hasMaterials,
        public bool $hasCertificate,
        public bool $accountCreatedNow,
        public ?string $setPasswordUrl = null
    ) {}

    public function build()
    {
        $participantFirstName = trim((string) $this->participant->first_name) ?: 'Uczestniku';
        $courseTitle = $this->course->title ?? 'szkolenie';

        return $this->subject('Dostęp do materiałów i nagrania – '.$courseTitle.' – '.config('app.name'))
            ->view('emails.course-access')
            ->with([
                'participantFirstName' => $participantFirstName,
                'courseTitle' => $courseTitle,
                'courseUrl' => $this->courseUrl,
                'hasVideos' => $this->hasVideos,
                'hasMaterials' => $this->hasMaterials,
                'hasCertificate' => $this->hasCertificate,
                'accountCreatedNow' => $this->accountCreatedNow,
                'setPasswordUrl' => $this->setPasswordUrl,
            ]);
    }
}
