<?php

namespace App\Notifications;

use App\Notifications\Concerns\FormatsPneduProvisionEmailDetails;
use App\Notifications\Concerns\UsesSystemMailSettings;
use App\Support\PneduProvisionLiveAccessContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ParticipantLiveMeetingLinkNotification extends Notification
{
    use FormatsPneduProvisionEmailDetails;
    use Queueable;
    use UsesSystemMailSettings;

    public function __construct(
        protected string $courseTitle,
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

        $message = $this->configureSystemMail(new MailMessage)
            ->subject('Spotkanie na żywo — '.$this->plainCourseTitle())
            ->greeting($greeting)
            ->line('Przesyłamy bezpośredni link do udziału w szkoleniu na żywo.')
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

        if ($html = $this->liveMeetingSectionHtml()) {
            $message->line($html);
        }

        $message->line(new HtmlString(
            '<p style="margin:18px 0 8px 0;line-height:1.45;">'
            .'<strong style="font-size:16px;">Twoje konto na pnedu.pl</strong>'
            .'</p>'
            .'<p style="margin:0 0 0 0;line-height:1.45;">'
            .'Ten sam link znajdziesz także po zalogowaniu: zakładka <strong>Twoje szkolenia</strong> — przycisk dołączenia do spotkania (przed startem i w trakcie szkolenia).'
            .'</p>'
        ));

        if ($this->liveAccess->joinUrl) {
            $message->action('Dołącz do spotkania na żywo', $this->liveAccess->joinUrl);
        }

        return $message
            ->line(new HtmlString(
                '<p style="margin:16px 0 0 0;line-height:1.45;">'
                .'<a href="'.e($this->dashboardSzkoleniaUrl).'" style="color:#0d6efd;">Przejdź do listy szkoleń na pnedu.pl</a>'
                .'</p>'
            ))
            ->line('Jeśli nie zapisywałeś/aś się na to szkolenie, skontaktuj się z biurem.');
    }

    private function plainCourseTitle(): string
    {
        return trim(str_replace(['&nbsp;', "\xc2\xa0"], ' ', strip_tags(html_entity_decode($this->courseTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    }

    private function liveMeetingSectionHtml(): ?HtmlString
    {
        $live = $this->liveAccess;
        if (! $live->showLiveSection || ! $live->joinUrl) {
            return null;
        }

        $parts = [];
        $platformLabel = e($live->platformLabel ?? 'Spotkanie online');
        $parts[] = '<p style="margin:18px 0 8px 0;line-height:1.45;">'
            .'<strong style="font-size:16px;">Spotkanie na żywo ('.$platformLabel.')</strong>'
            .'</p>';

        if ($live->showSpamNote) {
            $parts[] = '<p style="margin:0 0 10px 0;line-height:1.45;color:#6c757d;font-size:14px;">'
                .'Osobne zaproszenie od ClickMeeting mogło trafić do folderu SPAM lub Oferty. '
                .'Poniższy link działa niezależnie od zaproszenia systemowego ClickMeeting.'
                .'</p>';
        }

        $url = e($live->joinUrl);
        $parts[] = '<p style="margin:0 0 8px 0;line-height:1.45;">'
            .'<span style="color:#6c757d;font-size:13px;font-weight:600;">Link do spotkania:</span><br>'
            .'<a href="'.$url.'" style="color:#0d6efd;font-size:15px;font-weight:600;word-break:break-all;">'.$url.'</a>'
            .'</p>';

        if ($live->token) {
            $parts[] = '<p style="margin:0 0 8px 0;line-height:1.45;">'
                .'<span style="color:#6c757d;font-size:13px;font-weight:600;">Token dostępu:</span> '
                .'<span style="font-size:16px;font-weight:600;">'.e($live->token).'</span> '
                .'<span style="color:#6c757d;font-size:13px;">(przypisany do Twojego adresu e-mail)</span>'
                .'</p>';
        }

        if ($live->hasPassword()) {
            $parts[] = '<p style="margin:0 0 8px 0;line-height:1.45;">'
                .'<span style="color:#6c757d;font-size:13px;font-weight:600;">Hasło do spotkania:</span> '
                .'<span style="font-size:16px;font-weight:600;">'.e($live->password).'</span>'
                .'</p>';
        }

        $parts[] = '<p style="margin:0 0 0 0;line-height:1.45;color:#6c757d;font-size:14px;">'
            .'Wejdź kilka minut przed rozpoczęciem. Przy dołączaniu podaj imię oraz ten sam adres e-mail, którym jesteś zapisany/a na szkolenie.'
            .'</p>';

        return new HtmlString(implode('', $parts));
    }
}
