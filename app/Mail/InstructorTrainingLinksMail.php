<?php

namespace App\Mail;

use App\Models\Course;
use App\Support\PlainTextEmailHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InstructorTrainingLinksMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Course $course,
        public string $plainBody,
        public string $subjectLine,
    ) {}

    public function build(): self
    {
        $htmlBody = PlainTextEmailHtml::formatTrainingLinksEmailHtml($this->plainBody);

        return $this->subject($this->subjectLine)
            ->view('emails.instructor-training-links')
            ->text('emails.instructor-training-links-text')
            ->with([
                'course' => $this->course,
                'plainBody' => $this->plainBody,
                'htmlBody' => $htmlBody,
            ]);
    }
}
