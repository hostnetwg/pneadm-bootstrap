<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\DataCompletionToken;
use Illuminate\Support\Collection;

class DataCompletionRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public DataCompletionToken $token;
    public Collection $courses;
    public string $participantName;
    public string $formUrl;
    public bool $isTestMode;

    /**
     * Create a new message instance.
     */
    public function __construct(DataCompletionToken $token, Collection $courses, string $participantName, bool $isTestMode = false)
    {
        $this->token = $token;
        $this->courses = $courses;
        $this->participantName = $participantName;
        $this->isTestMode = $isTestMode;
        $this->formUrl = route('data-completion.form', ['token' => $token->token]);
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = $this->isTestMode 
            ? '[TEST] Prośba o uzupełnienie danych do rejestru zaświadczeń'
            : 'Prośba o uzupełnienie danych do rejestru zaświadczeń';
        
        // Użyj specjalnego adresu dla modułu uzupełniania danych
        // FROM: biuro@nowoczesna-edukacja.pl (nowa skrzynka, mniejsze ryzyko spamu)
        // Reply-To: kontakt@nowoczesna-edukacja.pl (główna skrzynka kontaktowa)
        // Uwaga: Autoryzacja SMTP musi być na kontakt@nowoczesna-edukacja.pl,
        // ale FROM może być biuro@ jeśli serwer to obsługuje
        $fromAddress = config('mail.data_completion.from_address', 'biuro@nowoczesna-edukacja.pl');
        $fromName = config('mail.data_completion.from_name', 'NODN Platforma Nowoczesnej Edukacji');
        $replyToAddress = config('mail.data_completion.reply_to_address', 'kontakt@nowoczesna-edukacja.pl');
        $replyToName = config('mail.data_completion.reply_to_name', $fromName);
            
        return $this->subject($subject)
                    ->from($fromAddress, $fromName)
                    ->replyTo($replyToAddress, $replyToName)
                    ->view('data-completion.email')
                    ->with([
                        'token' => $this->token,
                        'courses' => $this->courses,
                        'participantName' => $this->participantName,
                        'formUrl' => $this->formUrl,
                        'isTestMode' => $this->isTestMode,
                    ]);
    }
}

