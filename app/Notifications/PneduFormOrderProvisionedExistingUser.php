<?php

namespace App\Notifications;

use App\Notifications\Concerns\FormatsPneduProvisionEmailDetails;
use App\Notifications\Concerns\FormatsPneduProvisionLiveAccess;
use App\Notifications\Concerns\UsesSystemMailSettings;
use App\Support\PneduProvisionLiveAccessContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PneduFormOrderProvisionedExistingUser extends Notification
{
    use FormatsPneduProvisionEmailDetails;
    use FormatsPneduProvisionLiveAccess;
    use Queueable;
    use UsesSystemMailSettings;

    public function __construct(
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
        $loginUrl = $base.'/login?'.http_build_query([
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $liveAccess = $this->liveAccess ?? new PneduProvisionLiveAccessContext;

        $hasInstructor = $this->instructorLine !== null && $this->instructorLine !== '';
        $hasDate = $this->startDateLine !== null && $this->startDateLine !== '';

        $message = $this->configureSystemMail(new MailMessage)
            ->subject('Dostęp do szkolenia: '.$this->plainCourseTitleForSubject($this->courseTitle).' - zaloguj się.')
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
            $liveAccess->showLiveSection || $liveAccess->showPostEventSection ? null : '1em'
        )) {
            $message->line($html);
        }

        $loginAfterEmbedNote = $this->shouldPlaceLoginActionAfterEmbedNote($liveAccess);

        if ($loginAfterEmbedNote) {
            if ($html = $this->liveAccessSectionIntroHtml($liveAccess)) {
                $message->line($html);
            }
            $message->action('Zaloguj się na pnedu.pl', $loginUrl);
            if ($html = $this->liveAccessSectionLinksHtml($liveAccess)) {
                $message->line($html);
            }
        } elseif ($html = $this->liveAccessSectionHtml($liveAccess)) {
            $message->line($html);
        }

        if ($html = $this->postEventSectionHtml($liveAccess)) {
            $message->line($html);
        }

        if (! $loginAfterEmbedNote) {
            $message->action('Zaloguj się na pnedu.pl', $loginUrl);
        }

        return $message
            ->line('Jeśli nie pamiętasz hasła, na stronie logowania użyj opcji przypomnienia / resetu hasła.')
            ->line('Jeśli to nie Ty zapisałeś się na szkolenie, skontaktuj się z biurem.');
    }

    private function shouldPlaceLoginActionAfterEmbedNote(PneduProvisionLiveAccessContext $liveAccess): bool
    {
        return $liveAccess->showLiveSection
            && $liveAccess->usesEmbeddedJoin
            && ! $liveAccess->requiresPasswordSetup;
    }
}
