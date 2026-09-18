<?php

namespace App\Mail;

use App\Mail\Concerns\UsesSystemMailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChangelogVersionReleasedMail extends Mailable
{
    use Queueable, SerializesModels, UsesSystemMailSettings;

    /**
     * @param  list<string>  $bullets
     */
    public function __construct(
        public string $appLabel,
        public string $version,
        public ?string $date,
        public array $bullets,
        public string $changelogUrl,
        public string $recipientName,
    ) {}

    public function build()
    {
        return $this->withSystemMailSettings()
            ->subject($this->appLabel.' v '.$this->version.' — nowa wersja')
            ->view('emails.changelog-version-released')
            ->with([
                'appLabel' => $this->appLabel,
                'version' => $this->version,
                'date' => $this->date,
                'bullets' => $this->bullets,
                'changelogUrl' => $this->changelogUrl,
                'recipientName' => $this->recipientName,
            ]);
    }
}
