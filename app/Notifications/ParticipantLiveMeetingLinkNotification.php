<?php

namespace App\Notifications;

use App\Notifications\Concerns\FormatsPneduProvisionEmailDetails;
use App\Notifications\Concerns\FormatsPneduProvisionLiveAccess;
use App\Notifications\Concerns\UsesSystemMailSettings;
use App\Support\PneduProvisionLiveAccessContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ParticipantLiveMeetingLinkNotification extends Notification
{
    use FormatsPneduProvisionEmailDetails;
    use FormatsPneduProvisionLiveAccess;
    use Queueable;
    use UsesSystemMailSettings;

    public function __construct(
        protected string $courseTitle,
        protected string $participantEmail,
        protected string $participantFirstName,
        protected ?string $instructorLine,
        protected ?string $scheduleLine,
        protected PneduProvisionLiveAccessContext $liveAccess,
        protected string $dashboardSzkoleniaUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = trim($this->participantFirstName);
        $greeting = $firstName !== '' ? 'Witaj, '.$firstName.'!' : 'Witaj!';
        $base = rtrim((string) config('services.pnedu_frontend_url'), '/');
        $loginUrl = $base.'/login?'.http_build_query([
            'email' => $this->participantEmail,
        ]);
        $loginAfterEmbedNote = $this->usesEmbeddedJoinWithLiveSection();

        $message = $this->configureSystemMail(new MailMessage)
            ->subject('Dostęp do szkolenia: '.$this->plainCourseTitleForSubject($this->courseTitle).' - spotkanie na żywo.')
            ->greeting($greeting)
            ->line($loginAfterEmbedNote
                ? 'Zbliża się szkolenie na żywo — najwygodniej dołączysz przez pnedu.pl (zalecamy zalogować się przed startem).'
                : 'Przesyłamy link do udziału w szkoleniu na żywo.')
            ->line($this->courseTitleOnlyHtml(
                $this->courseTitle,
                ($this->instructorLine || $this->scheduleLine) ? null : '1em'
            ));

        if ($html = $this->colonPrefixedDetailHtml(
            $this->instructorLine,
            '6px',
            $this->scheduleLine ? null : '1em'
        )) {
            $message->line($html);
        }

        if ($html = $this->colonPrefixedDetailHtml(
            $this->scheduleLine,
            $this->instructorLine ? '0' : '6px',
            '1em'
        )) {
            $message->line($html);
        }

        if ($loginAfterEmbedNote) {
            if ($html = $this->liveAccessSectionIntroHtml($this->liveAccess)) {
                $message->line($html);
            }
            $message->action('Zaloguj się na pnedu.pl', $loginUrl);
            if ($html = $this->liveAccessSectionLinksHtml($this->liveAccess)) {
                $message->line($html);
            }
        } else {
            if ($html = $this->liveAccessSectionHtml($this->liveAccess)) {
                $message->line($html);
            }
            if ($this->liveAccess->joinUrl) {
                $message->action('Dołącz do spotkania na żywo', $this->liveAccess->joinUrl);
            }
        }

        return $message
            ->line(new HtmlString(
                '<p style="margin:18px 0 8px 0;line-height:1.45;">'
                .'<strong style="font-size:16px;">Twoje konto na pnedu.pl</strong>'
                .'</p>'
                .'<p style="margin:0 0 0 0;line-height:1.45;">'
                .'Ten sam link znajdziesz także po zalogowaniu w zakładce <strong>Twoje szkolenia</strong> — przycisk dołączenia aktywuje się ok. 2 godziny przed startem i działa w trakcie szkolenia.'
                .'</p>'
            ))
            ->line(new HtmlString(
                '<p style="margin:16px 0 0 0;line-height:1.45;">'
                .'<a href="'.e($this->dashboardSzkoleniaUrl).'" style="color:#0d6efd;">Przejdź do listy szkoleń na pnedu.pl</a>'
                .'</p>'
            ))
            ->line('Jeśli nie pamiętasz hasła, na stronie logowania użyj opcji przypomnienia / resetu hasła.')
            ->line('Jeśli nie zapisywałeś/aś się na to szkolenie, skontaktuj się z biurem.');
    }

    private function usesEmbeddedJoinWithLiveSection(): bool
    {
        return $this->liveAccess->showLiveSection
            && $this->liveAccess->usesEmbeddedJoin;
    }
}
