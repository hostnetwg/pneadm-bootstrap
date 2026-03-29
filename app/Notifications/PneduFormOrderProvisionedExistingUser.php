<?php

namespace App\Notifications;

use App\Notifications\Concerns\FormatsPneduProvisionEmailDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PneduFormOrderProvisionedExistingUser extends Notification
{
    use FormatsPneduProvisionEmailDetails;
    use Queueable;

    public function __construct(
        protected string $courseTitle,
        protected ?string $instructorLine = null,
        protected ?string $startDateLine = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('services.pnedu_frontend_url'), '/');
        $loginUrl = $base.'/login';

        $hasInstructor = $this->instructorLine !== null && $this->instructorLine !== '';
        $hasDate = $this->startDateLine !== null && $this->startDateLine !== '';

        $message = (new MailMessage)
            ->subject('Platforma PNEDU — dostęp do szkolenia')
            ->greeting('Witaj!')
            ->line('Przypisaliśmy Ci dostęp do szkolenia na pnedu.pl (masz już konto z tym adresem e-mail).')
            ->line($this->courseTitleOnlyHtml(
                $this->courseTitle,
                (! $hasInstructor && ! $hasDate) ? '1em' : null
            ));

        if ($html = $this->colonPrefixedDetailHtml(
            $this->instructorLine,
            '6px',
            $hasDate ? null : '1em'
        )) {
            $message->line($html);
        }
        if ($html = $this->colonPrefixedDetailHtml(
            $this->startDateLine,
            $hasInstructor ? '0' : '6px',
            '1em'
        )) {
            $message->line($html);
        }

        return $message
            ->action('Zaloguj się na pnedu.pl', $loginUrl)
            ->line('Jeśli nie pamiętasz hasła, na stronie logowania użyj opcji przypomnienia / resetu hasła.')
            ->line('Jeśli to nie Ty zapisałeś się na szkolenie, skontaktuj się z biurem.');
    }
}
