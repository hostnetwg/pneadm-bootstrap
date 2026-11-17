<?php

namespace App\Mail;

use App\Mail\Concerns\UsesSystemMailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OnlineCourseMigrationNoticeMail extends Mailable
{
    use Queueable, SerializesModels, UsesSystemMailSettings;

    /**
     * @param  list<string>  $courseTitles
     */
    public function __construct(
        public string $participantFirstName,
        public string $participantEmail,
        public array $courseTitles,
        public bool $hasPneduAccount,
        public string $loginUrl,
        public string $registerUrl,
        public string $forgotPasswordUrl,
        public string $coursesUrl,
    ) {}

    public function build()
    {
        return $this->withSystemMailSettings()
            ->subject('Przeniesienie kursów na pnedu.pl')
            ->view('emails.online-course-migration-notice')
            ->with([
                'participantFirstName' => $this->participantFirstName !== '' ? $this->participantFirstName : 'Uczestniku',
                'participantEmail' => $this->participantEmail,
                'courseTitles' => $this->courseTitles,
                'hasPneduAccount' => $this->hasPneduAccount,
                'loginUrl' => $this->loginUrl,
                'registerUrl' => $this->registerUrl,
                'forgotPasswordUrl' => $this->forgotPasswordUrl,
                'coursesUrl' => $this->coursesUrl,
            ]);
    }
}
