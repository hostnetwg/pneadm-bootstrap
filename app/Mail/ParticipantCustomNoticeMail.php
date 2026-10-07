<?php

namespace App\Mail;

use App\Mail\Concerns\UsesSystemMailSettings;
use App\Models\Course;
use App\Support\PlainTextEmailHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ParticipantCustomNoticeMail extends Mailable
{
    use Queueable, SerializesModels, UsesSystemMailSettings;

    public function __construct(
        public Course $course,
        public string $plainBody,
        public string $subjectLine,
    ) {}

    public function build(): self
    {
        return $this->withSystemMailSettings()
            ->subject($this->subjectLine)
            ->view('emails.participant-custom-notice')
            ->text('emails.participant-custom-notice-text')
            ->with([
                'course' => $this->course,
                'plainBody' => $this->plainBody,
                'htmlBody' => PlainTextEmailHtml::linkifyForEmail($this->plainBody),
            ]);
    }
}
