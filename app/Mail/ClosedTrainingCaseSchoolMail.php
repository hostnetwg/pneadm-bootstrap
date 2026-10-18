<?php

namespace App\Mail;

use App\Mail\Concerns\UsesSystemMailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClosedTrainingCaseSchoolMail extends Mailable
{
    use Queueable, SerializesModels, UsesSystemMailSettings;

    public function __construct(
        public string $htmlBody,
        public string $subjectLine,
    ) {}

    public function build(): self
    {
        $plainBody = trim(html_entity_decode(strip_tags($this->htmlBody), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $this->withSystemMailSettings()
            ->subject($this->subjectLine)
            ->view('emails.closed-training-case-school')
            ->text('emails.closed-training-case-school-text')
            ->with([
                'htmlBody' => $this->htmlBody,
                'plainBody' => $plainBody,
                'subjectLine' => $this->subjectLine,
            ]);
    }
}
