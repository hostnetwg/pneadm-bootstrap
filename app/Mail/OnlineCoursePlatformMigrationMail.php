<?php

namespace App\Mail;

use App\Mail\Concerns\UsesSystemMailSettings;
use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OnlineCoursePlatformMigrationMail extends Mailable
{
    use Queueable, SerializesModels, UsesSystemMailSettings;

    public function __construct(
        public OnlineCourseEnrollment $enrollment,
        public OnlineCourse $course,
        public bool $hasPneduAccount,
        public string $participantEmail,
        public string $loginUrl,
        public string $registerUrl,
        public string $forgotPasswordUrl,
        public ?string $courseUrl,
        public ?string $accessExpiresAtFormatted,
        public bool $accessExpired,
    ) {}

    public function build()
    {
        $firstName = trim((string) $this->enrollment->first_name) ?: 'Uczestniku';
        $courseTitle = $this->plainCourseTitle();

        return $this->withSystemMailSettings()
            ->subject('Przeniesienie kursu na pnedu.pl: '.$courseTitle)
            ->view('emails.online-course-platform-migration')
            ->with([
                'participantFirstName' => $firstName,
                'courseTitle' => $courseTitle,
                'participantEmail' => $this->participantEmail,
                'hasPneduAccount' => $this->hasPneduAccount,
                'loginUrl' => $this->loginUrl,
                'registerUrl' => $this->registerUrl,
                'forgotPasswordUrl' => $this->forgotPasswordUrl,
                'courseUrl' => $this->courseUrl,
                'accessExpiresAtFormatted' => $this->accessExpiresAtFormatted,
                'accessExpired' => $this->accessExpired,
            ]);
    }

    private function plainCourseTitle(): string
    {
        $plain = trim(str_replace(
            ['&nbsp;', "\xc2\xa0"],
            ' ',
            strip_tags(html_entity_decode((string) ($this->course->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ));
        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);

        return $plain !== '' ? $plain : 'kurs online';
    }
}
