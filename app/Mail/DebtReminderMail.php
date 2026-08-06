<?php

namespace App\Mail;

use App\Mail\Concerns\UsesSystemMailSettings;
use App\Support\PlainTextEmailHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DebtReminderMail extends Mailable
{
    use Queueable, SerializesModels, UsesSystemMailSettings;

    /**
     * @param  list<array{path: string, name: string, mime?: string}>  $fileAttachments
     */
    public function __construct(
        public string $plainBody,
        public string $subjectLine,
        public array $fileAttachments = [],
    ) {}

    public function build(): self
    {
        $htmlBody = PlainTextEmailHtml::linkifyForEmail($this->plainBody);

        $mail = $this->withSystemMailSettings()
            ->subject($this->subjectLine)
            ->view('emails.debt-reminder')
            ->text('emails.debt-reminder-text')
            ->with([
                'plainBody' => $this->plainBody,
                'htmlBody' => $htmlBody,
            ]);

        foreach ($this->fileAttachments as $attachment) {
            $mail->attach($attachment['path'], [
                'as' => $attachment['name'],
                'mime' => $attachment['mime'] ?? 'application/pdf',
            ]);
        }

        return $mail;
    }
}
