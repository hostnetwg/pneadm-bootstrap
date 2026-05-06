<?php

namespace App\Support;

class PlainTextEmailHtml
{
    /**
     * Escapuje zwykły tekst i zamienia wykryte adresy URL na klikalne odnośniki (&lt;a href&gt;).
     * Zawartość wyświetlana z {@see white-space: pre-wrap}, żeby zachować łamanie linii i wcięcia.
     */
    public static function linkifyForEmail(string $plain): string
    {
        $pattern = '/(https?:\/\/[^\s<>"\']+|www\.[^\s<>"\']+)/iu';

        $parts = preg_split($pattern, $plain, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match($pattern, $part)) {
                $href = $part;
                if (preg_match('/^www\./iu', $href)) {
                    $href = 'https://'.$href;
                }
                $safeUrl = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeHref = htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $out .= '<a href="'.$safeHref.'" style="color:#0d6efd;text-decoration:underline;">'.$safeUrl.'</a>';
            } else {
                $out .= htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        return $out;
    }

    /**
     * HTML dla wiadomości „linki do szkolenia”: jak {@see linkifyForEmail()}, ale dwie linie po
     * „poniżej przesyłam linki na szkolenie:” (tytuł i data) są w &lt;strong&gt;.
     * Dwie pierwsze niepuste linie po tym zdaniu traktowane są jako tytuł i data (puste linie pomijane).
     * Nagłówki sekcji „N) MATERIAŁY:” oraz „N) ANKIETA:” (N – numer) też są pogrubione.
     */
    public static function formatTrainingLinksEmailHtml(string $plainBody): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $plainBody);
        if ($lines === false) {
            return self::linkifyForEmail($plainBody);
        }

        [$titleIdx, $dateIdx] = self::trainingLinksTitleAndDateLineIndexes($lines);

        $out = '';
        $last = count($lines) - 1;
        foreach ($lines as $idx => $line) {
            $trimmed = trim($line);
            if ($titleIdx !== null && $dateIdx !== null && ($idx === $titleIdx || $idx === $dateIdx)) {
                $safe = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $out .= '<strong style="font-weight:700;">'.$safe.'</strong>';
            } elseif (self::isMaterialOrSurveySectionHeading($trimmed)) {
                $safe = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $out .= '<strong style="font-weight:700;">'.$safe.'</strong>';
            } else {
                $out .= self::linkifyForEmail($line);
            }
            if ($idx !== $last) {
                $out .= "\n";
            }
        }

        return $out;
    }

    /**
     * @return array{0: int|null, 1: int|null} indeksy linii tytułu i daty
     */
    private static function trainingLinksTitleAndDateLineIndexes(array $lines): array
    {
        foreach ($lines as $idx => $line) {
            if (trim($line) !== 'poniżej przesyłam linki na szkolenie:') {
                continue;
            }
            $nonEmptyIndexes = [];
            $max = count($lines);
            for ($j = $idx + 1; $j < $max && count($nonEmptyIndexes) < 2; $j++) {
                if (trim($lines[$j]) !== '') {
                    $nonEmptyIndexes[] = $j;
                }
            }
            if (count($nonEmptyIndexes) === 2) {
                return [$nonEmptyIndexes[0], $nonEmptyIndexes[1]];
            }

            break;
        }

        return [null, null];
    }

    private static function isMaterialOrSurveySectionHeading(string $trimmedLine): bool
    {
        return (bool) preg_match('/^\d+\)\s*MATERIAŁY:\s*$/u', $trimmedLine)
            || (bool) preg_match('/^\d+\)\s*ANKIETA:\s*$/u', $trimmedLine);
    }
}
