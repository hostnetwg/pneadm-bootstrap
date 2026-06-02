<?php

namespace App\Mail;

use App\Mail\Concerns\UsesSystemMailSettings;
use App\Models\DataCompletionToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DataCompletionRequestMail extends Mailable
{
    use Queueable, SerializesModels, UsesSystemMailSettings;

    public DataCompletionToken $token;

    public Collection $courses;

    public string $participantName;

    public string $formUrl;

    public bool $isTestMode;

    public function __construct(DataCompletionToken $token, Collection $courses, string $participantName, bool $isTestMode = false)
    {
        $this->token = $token;
        $this->courses = $courses;
        $this->participantName = $participantName;
        $this->isTestMode = $isTestMode;
        $this->formUrl = route('data-completion.form', ['token' => $token->token]);
    }

    public function build()
    {
        $subject = $this->isTestMode
            ? '[TEST] Prośba o uzupełnienie danych do rejestru zaświadczeń'
            : 'Prośba o uzupełnienie danych do rejestru zaświadczeń';

        return $this->withSystemMailSettings()
            ->subject($subject)
            ->view('data-completion.email')
            ->with([
                'token' => $this->token,
                'courses' => $this->courses,
                'participantName' => $this->participantName,
                'formUrl' => $this->formUrl,
                'isTestMode' => $this->isTestMode,
                'contactEmail' => config('mail.system.reply_to_address'),
                'brandPublicUrl' => config('mail.brand.public_url'),
                'brandPublicLabel' => config('mail.brand.public_label'),
            ]);
    }
}
