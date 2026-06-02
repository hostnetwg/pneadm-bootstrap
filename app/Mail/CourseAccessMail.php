<?php

namespace App\Mail;

use App\Mail\Concerns\UsesSystemMailSettings;
use App\Models\Course;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CourseAccessMail extends Mailable
{
    use Queueable, SerializesModels, UsesSystemMailSettings;

    /**
     * @param  list<array{title: string, url: string}>  $surveyLinks
     */
    public function __construct(
        public Participant $participant,
        public Course $course,
        public bool $hasPneduAccount,
        public ?string $courseUrl,
        public ?string $certificateUrl,
        public string $registerUrl,
        public string $participantEmail,
        public bool $hasVideos,
        public bool $hasMaterials,
        public bool $hasCertificate,
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

        $hasLimitedAccess = $this->participant->hasLimitedAccess();
        $accessExpired = $hasLimitedAccess ? $this->participant->hasExpiredAccess() : false;
        $accessExpiresAtFormatted = null;
        if ($hasLimitedAccess && $this->participant->access_expires_at) {
            $accessExpiresAtFormatted = $this->participant->access_expires_at
                ->copy()
                ->setTimezone('Europe/Warsaw')
                ->format('d.m.Y H:i');
        }

        $needsAccountForRecordings = ! $this->hasPneduAccount && ($this->hasVideos || $this->hasMaterials);

        return $this->withSystemMailSettings()
            ->subject('Dostęp do materiałów i nagrania – '.$courseTitle.' – '.config('app.name'))
            ->view('emails.course-access')
            ->with([
                'participantFirstName' => $participantFirstName,
                'courseTitle' => $courseTitle,
                'courseUrl' => $this->courseUrl,
                'certificateUrl' => $this->certificateUrl,
                'registerUrl' => $this->registerUrl,
                'participantEmail' => $this->participantEmail,
                'hasPneduAccount' => $this->hasPneduAccount,
                'needsAccountForRecordings' => $needsAccountForRecordings,
                'hasVideos' => $this->hasVideos,
                'hasMaterials' => $this->hasMaterials,
                'hasCertificate' => $this->hasCertificate,
                'courseDateLong' => $courseDateLong,
                'hasLimitedAccess' => $hasLimitedAccess,
                'accessExpired' => $accessExpired,
                'accessExpiresAtFormatted' => $accessExpiresAtFormatted,
                'surveyLinks' => $this->surveyLinks,
            ]);
    }
}
