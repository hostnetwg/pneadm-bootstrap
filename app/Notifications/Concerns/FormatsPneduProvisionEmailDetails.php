<?php

namespace App\Notifications\Concerns;

use Illuminate\Support\HtmlString;

trait FormatsPneduProvisionEmailDetails
{
    private const DETAIL_COLOR_COURSE = '#0d6efd';

    /** Kolor treści (imię i nazwisko, data) — bez akcentu, spójny z tekstem maila */
    private const DETAIL_VALUE_TEXT = '#212529';

    /**
     * Tylko tytuł szkolenia (bez etykiety „Szkolenie:” — kontekst jest w zdaniu powyżej).
     */
    protected function courseTitleOnlyHtml(string $courseTitlePlain, ?string $marginBottom = null): HtmlString
    {
        $value = e(str_replace('&nbsp;', ' ', strip_tags($courseTitlePlain)));
        $mb = $marginBottom ?? '0';

        return new HtmlString(
            '<p style="margin:12px 0 '.$mb.' 0;line-height:1.45;">'
            .'<strong style="color:'.self::DETAIL_COLOR_COURSE.';font-size:18px;line-height:1.25;">'.$value.'</strong>'
            .'</p>'
        );
    }

    /**
     * @param  string  $marginTop  np. „6px” (zwarto po tytule), „0” (bez odstępu po poprzedniej linii szczegółów)
     * @param  string|null  $marginBottom  np. „1em” — odstęp jednej linii przed kolejnym akapitem (np. po dacie)
     */
    protected function colonPrefixedDetailHtml(
        ?string $line,
        string $marginTop = '14px',
        ?string $marginBottom = null
    ): ?HtmlString {
        if ($line === null || $line === '') {
            return null;
        }

        $line = strip_tags($line);
        if (preg_match('/^(.+?):\s*(.+)$/su', $line, $m)) {
            $label = e(trim($m[1]));
            $value = e(trim($m[2]));
            $marginBottomShorthand = $marginBottom ?? '0';

            return new HtmlString(
                '<p style="margin:'.$marginTop.' 0 '.$marginBottomShorthand.' 0;line-height:1.45;">'
                .'<span style="color:#6c757d;font-size:13px;font-weight:600;">'.$label.':</span> '
                .'<span style="color:'.self::DETAIL_VALUE_TEXT.';font-size:16px;font-weight:600;line-height:1.3;">'.$value.'</span>'
                .'</p>'
            );
        }

        return new HtmlString('<p style="margin:'.$marginTop.' 0 0;line-height:1.45;">'.e($line).'</p>');
    }
}
