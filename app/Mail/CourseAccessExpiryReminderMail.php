<?php

namespace App\Mail;

use App\Mail\Concerns\UsesSystemMailSettings;
use App\Models\Course;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CourseAccessExpiryReminderMail extends Mailable
{
    use Queueable, SerializesModels, UsesSystemMailSettings;

    /**
     * @param  list<array{title: string, url: string}>  $surveyLinks
     */
    public function __construct(
        public Participant $participant,
        public Course $course,
        public int $daysBefore,
        public bool $hasPneduAccount,
        public ?string $courseUrl,
        public ?string $certificateUrl,
        public string $registerUrl,
        public string $participantEmail,
        public bool $hasVideos,
        public bool $hasMaterials,
        public bool $hasCertificate,
        public string $accessExpiresAtFormatted,
        public array $surveyLinks = []
    ) {}

    public function build()
    {
        $participantFirstName = trim((string) $this->participant->first_name) ?: 'Uczestniku';
        $courseTitle = $this->course->title ?? 'szkolenie';

        $courseDateLong = null;
        if ($this->course->start_date) {
            $courseDateLong = $this->course->start_date
                ->copy()
                ->setTimezone('Europe/Warsaw')
                ->locale('pl')
                ->translatedFormat('j F Y \\r.');
        }

        $needsAccountForRecordings = ! $this->hasPneduAccount && ($this->hasVideos || $this->hasMaterials);

        $timingLabel = match (true) {
            $this->daysBefore === 0 => 'dziś',
            $this->daysBefore === 1 => 'jutro',
            default => 'za '.$this->daysBefore.' dni',
        };

        return $this->withSystemMailSettings()
            ->subject('Przypomnienie: '.$timingLabel.' wygasa dostęp do nagrania – '.$courseTitle.' – '.config('app.name'))
            ->view('emails.course-access-expiry-reminder')
            ->with([
                'participantFirstName' => $participantFirstName,
                'courseTitle' => $courseTitle,
                'courseDateLong' => $courseDateLong,
                'daysBefore' => $this->daysBefore,
                'timingLabel' => $timingLabel,
                'accessExpiresAtFormatted' => $this->accessExpiresAtFormatted,
                'courseUrl' => $this->courseUrl,
                'certificateUrl' => $this->certificateUrl,
                'registerUrl' => $this->registerUrl,
                'participantEmail' => $this->participantEmail,
                'hasPneduAccount' => $this->hasPneduAccount,
                'needsAccountForRecordings' => $needsAccountForRecordings,
                'hasVideos' => $this->hasVideos,
                'hasMaterials' => $this->hasMaterials,
                'hasCertificate' => $this->hasCertificate,
                'surveyLinks' => $this->surveyLinks,
            ]);
    }
}
