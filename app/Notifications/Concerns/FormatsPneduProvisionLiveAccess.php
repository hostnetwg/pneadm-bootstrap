<?php

namespace App\Notifications\Concerns;

use App\Support\PneduProvisionLiveAccessContext;
use Illuminate\Support\HtmlString;

trait FormatsPneduProvisionLiveAccess
{
    protected function liveAccessSectionHtml(PneduProvisionLiveAccessContext $liveAccess): ?HtmlString
    {
        if (! $liveAccess->showLiveSection) {
            return null;
        }

        $parts = [];
        $platformLabel = e($liveAccess->platformLabel ?? 'Spotkanie online');
        $parts[] = '<p style="margin:18px 0 8px 0;line-height:1.45;">'
            .'<strong style="font-size:16px;">Spotkanie na żywo ('.$platformLabel.')</strong>'
            .'</p>';

        if ($liveAccess->showSpamNote) {
            $parts[] = '<p style="margin:0 0 10px 0;line-height:1.45;color:#6c757d;font-size:14px;">'
                .'Osobne zaproszenie od ClickMeeting mogło trafić do folderu SPAM lub Oferty. '
                .'Poniżej przesyłamy bezpośredni link — możesz z niego skorzystać niezależnie od zaproszenia systemowego.'
                .'</p>';
        }

        if ($liveAccess->joinUrl) {
            $url = e($liveAccess->joinUrl);
            $parts[] = '<p style="margin:0 0 8px 0;line-height:1.45;">'
                .'<span style="color:#6c757d;font-size:13px;font-weight:600;">Link do spotkania:</span><br>'
                .'<a href="'.$url.'" style="color:#0d6efd;font-size:15px;font-weight:600;word-break:break-all;">'.$url.'</a>'
                .'</p>';
        }

        if ($liveAccess->token) {
            $parts[] = '<p style="margin:0 0 8px 0;line-height:1.45;">'
                .'<span style="color:#6c757d;font-size:13px;font-weight:600;">Token dostępu:</span> '
                .'<span style="font-size:16px;font-weight:600;">'.e($liveAccess->token).'</span> '
                .'<span style="color:#6c757d;font-size:13px;">(jednorazowy)</span>'
                .'</p>';
        }

        if ($liveAccess->hasPassword()) {
            $parts[] = '<p style="margin:0 0 8px 0;line-height:1.45;">'
                .'<span style="color:#6c757d;font-size:13px;font-weight:600;">Hasło do spotkania:</span> '
                .'<span style="font-size:16px;font-weight:600;">'.e($liveAccess->password).'</span>'
                .'</p>';
        }

        $parts[] = '<p style="margin:0 0 0 0;line-height:1.45;color:#6c757d;font-size:14px;">'
            .'Wejdź na spotkanie kilka minut przed rozpoczęciem. Przy logowaniu podaj imię, nazwisko i adres e-mail zapisany na szkolenie.'
            .'</p>';

        return new HtmlString(implode('', $parts));
    }

    protected function postEventSectionHtml(PneduProvisionLiveAccessContext $liveAccess): ?HtmlString
    {
        if (! $liveAccess->showPostEventSection) {
            return null;
        }

        $intro = $liveAccess->showLiveSection
            ? 'Po zakończeniu szkolenia na żywo na platformie pnedu.pl udostępnimy nagranie, materiały szkoleniowe oraz możliwość pobrania zaświadczenia — zgodnie z warunkami dostępu do tego szkolenia.'
            : 'Na platformie pnedu.pl masz dostęp do nagrania, materiałów szkoleniowych oraz zaświadczenia — zgodnie z warunkami dostępu do tego szkolenia. Zaloguj się i wejdź w swoje szkolenia.';

        return new HtmlString(
            '<p style="margin:18px 0 8px 0;line-height:1.45;">'
            .'<strong style="font-size:16px;">'.($liveAccess->showLiveSection ? 'Po zakończeniu szkolenia na żywo' : 'Materiały na pnedu.pl').'</strong>'
            .'</p>'
            .'<p style="margin:0 0 0 0;line-height:1.45;">'.e($intro).'</p>'
        );
    }
}
