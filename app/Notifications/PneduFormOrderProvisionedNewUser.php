<?php

namespace App\Notifications;

use App\Notifications\Concerns\FormatsPneduProvisionEmailDetails;
use App\Notifications\Concerns\FormatsPneduProvisionLiveAccess;
use App\Notifications\Concerns\UsesSystemMailSettings;
use App\Support\PneduProvisionLiveAccessContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PneduFormOrderProvisionedNewUser extends Notification
{
    use FormatsPneduProvisionEmailDetails;
    use FormatsPneduProvisionLiveAccess;
    use Queueable;
    use UsesSystemMailSettings;

    public function __construct(
        protected string $token,
        protected string $courseTitle,
        protected ?string $instructorLine = null,
        protected ?string $startDateLine = null,
        protected ?PneduProvisionLiveAccessContext $liveAccess = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('services.pnedu_frontend_url'), '/');
        $liveAccess = $this->liveAccess ?? new PneduProvisionLiveAccessContext;
        $url = $this->passwordSetupUrl(
            $base,
            $notifiable->getEmailForPasswordReset()
        );
        $liveAccess = $this->withPasswordSetupGate($liveAccess, $url);

        $hasInstructor = $this->instructorLine !== null && $this->instructorLine !== '';
        $hasDate = $this->startDateLine !== null && $this->startDateLine !== '';

        $message = $this->configureSystemMail(new MailMessage)
            ->subject('Dostęp do szkolenia: '.$this->plainCourseTitleForSubject($this->courseTitle).' - ustaw hasło.')
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
            null
        )) {
            $message->line($html);
        }

        $message
            ->line('Po ustawieniu hasła zobaczysz swoje szkolenia na pnedu.pl — przycisk dołączenia do spotkania na żywo aktywuje się 2 godziny przed startem.')
            ->action('Ustaw hasło na pnedu.pl', $url);

        if ($html = $this->liveAccessSectionHtml($liveAccess)) {
            $message->line($html);
        }
        if ($html = $this->postEventSectionHtml($liveAccess)) {
            $message->line($html);
        }

        $forgotUrl = $base.'/forgot-password';

        return $message
            ->line('Jeśli link wygasł lub nie działa, na stronie logowania użyj opcji „Nie pamiętam hasła”: '.$forgotUrl)
            ->line('Po zalogowaniu znajdziesz materiały powiązane z tym szkoleniem (zgodnie z dostępem przypisanym do Twojego konta).')
            ->line('Jeśli to nie Ty zapisałeś się na szkolenie, zignoruj tę wiadomość lub skontaktuj się z biurem.');
    }

    private function passwordSetupUrl(string $base, string $email): string
    {
        $query = [
            'email' => $email,
            'redirect' => '/dashboard/szkolenia',
        ];

        return $base.'/ustaw-haslo/'.$this->token.'?'.http_build_query($query);
    }

    private function withPasswordSetupGate(
        PneduProvisionLiveAccessContext $liveAccess,
        string $passwordSetupUrl
    ): PneduProvisionLiveAccessContext {
        if (! $liveAccess->usesEmbeddedJoin) {
            return $liveAccess;
        }

        return new PneduProvisionLiveAccessContext(
            showLiveSection: $liveAccess->showLiveSection,
            platformLabel: $liveAccess->platformLabel,
            joinUrl: $liveAccess->joinUrl,
            token: $liveAccess->token,
            password: $liveAccess->password,
            showSpamNote: $liveAccess->showSpamNote,
            showPostEventSection: $liveAccess->showPostEventSection,
            directJoinUrl: $liveAccess->directJoinUrl,
            usesEmbeddedJoin: $liveAccess->usesEmbeddedJoin,
            passwordSetupUrl: $passwordSetupUrl,
            requiresPasswordSetup: true,
        );
    }
}
