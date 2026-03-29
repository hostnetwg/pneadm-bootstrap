<?php

namespace App\Notifications;

use App\Notifications\Concerns\FormatsPneduProvisionEmailDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PneduFormOrderProvisionedNewUser extends Notification
{
    use FormatsPneduProvisionEmailDetails;
    use Queueable;

    public function __construct(
        protected string $token,
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
        $url = $base.'/reset-password/'.$this->token.'?email='.urlencode($notifiable->getEmailForPasswordReset());

        $hasInstructor = $this->instructorLine !== null && $this->instructorLine !== '';
        $hasDate = $this->startDateLine !== null && $this->startDateLine !== '';

        $message = (new MailMessage)
            ->subject('Platforma PNEDU — konto utworzone, ustaw hasło')
            ->greeting('Witaj!')
            ->line('Założyliśmy dla Ciebie konto na platformie pnedu.pl w związku z zapisem na szkolenie.')
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
            ->line('Aby się zalogować, najpierw ustaw hasło — kliknij przycisk poniżej. Link możesz wykorzystać w dowolnym momencie.')
            ->action('Ustaw hasło na pnedu.pl', $url)
            ->line('Po zalogowaniu znajdziesz materiały powiązane z tym szkoleniem (zgodnie z dostępem przypisanym do Twojego konta).')
            ->line('Jeśli to nie Ty zapisałeś się na szkolenie, zignoruj tę wiadomość lub skontaktuj się z biurem.');
    }
}
