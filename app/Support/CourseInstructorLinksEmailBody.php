<?php

namespace App\Support;

use App\Models\Course;

class CourseInstructorLinksEmailBody
{
    /**
     * Temat wiadomości dla prowadzącego / kopii testowej.
     */
    public static function subjectLine(Course $course): string
    {
        $title = strip_tags(html_entity_decode((string) ($course->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = trim(preg_replace('/\s+/u', ' ', $title));

        $when = $course->start_date
            ? $course->start_date->format('d.m.Y H:i')
            : 'brak daty';

        return 'Linki do szkolenia: '.($title !== '' ? $title : 'Szkolenie').' ('.$when.')';
    }

    /**
     * Treść prosta (plaintext) dla e-maila prowadzącego: powitanie + tylko sekcje, w których są faktyczne adresy URL.
     */
    public static function build(Course $course): string
    {
        $title = strip_tags(html_entity_decode((string) ($course->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = trim(preg_replace('/\s+/u', ' ', $title));

        $dateLine = 'Termin szkolenia: brak w systemie.';
        if ($course->start_date) {
            $dateLine = 'Termin szkolenia: '.$course->start_date->format('d.m.Y H:i').'.';
        }

        $lines = [
            'Dzień dobry,',
            'poniżej przesyłam linki na szkolenie:',
            $title !== '' ? $title : '[tytuł szkolenia]',
            $dateLine,
            '',
        ];

        $n = 1;

        // Nagrania
        $videoLines = [];
        foreach ($course->videos->sortBy('order') as $video) {
            $url = trim((string) ($video->video_url ?? ''));
            if ($url === '') {
                continue;
            }
            $label = trim((string) ($video->title ?? ''));
            if ($label !== '') {
                $videoLines[] = '   '.$label.': '.$url;
            } else {
                $videoLines[] = '   '.$url;
            }
        }
        if ($videoLines !== []) {
            $lines[] = "{$n}) NAGRANIA:";
            foreach ($videoLines as $vl) {
                $lines[] = $vl;
            }
            $lines[] = '';
            $n++;
        }

        // Materiały — bez tytułów, tylko URL (jak w ANKIETA)
        $materialUrls = [];
        foreach ($course->fileLinks->sortBy('order') as $fl) {
            $url = trim((string) ($fl->url ?? ''));
            if ($url !== '') {
                $materialUrls[] = $url;
            }
        }
        if ($materialUrls !== []) {
            if (count($materialUrls) === 1) {
                $lines[] = "{$n}) MATERIAŁY: ".$materialUrls[0];
            } else {
                $lines[] = "{$n}) MATERIAŁY:";
                foreach ($materialUrls as $url) {
                    $lines[] = '   '.$url;
                }
            }
            $lines[] = '';
            $n++;
        }

        // Lista obecności (rejestracja zaświadczenia na pnedu.pl)
        if ($course->isCertificateRegistrationActiveNow()) {
            $regUrl = $course->certificateRegistrationPublicUrl();
            if ($regUrl !== null) {
                $lines[] = "{$n}) LISTA OBECNOŚCI:";
                $lines[] = '   '.$regUrl;
                $lines[] = '';
                $n++;
            }
        }

        // Ankiety — bez tytułów, tylko URL uczestnika
        $surveyLines = [];
        foreach ($course->surveyLinks->sortBy('order') as $sl) {
            $participantUrl = trim((string) $sl->participantFacingSurveyUrl());
            if ($participantUrl === '') {
                continue;
            }
            $suffix = $sl->isAvailableNow() ? '' : ' (ankieta nieaktywna lub poza terminem dostępu w systemie)';
            $surveyLines[] = $participantUrl.$suffix;
        }
        if ($surveyLines !== []) {
            if (count($surveyLines) === 1) {
                $lines[] = "{$n}) ANKIETA: ".$surveyLines[0];
            } else {
                $lines[] = "{$n}) ANKIETA:";
                foreach ($surveyLines as $surveyLine) {
                    $lines[] = '   '.$surveyLine;
                }
            }
            $lines[] = '';
        }

        $lines[] = 'Pozdrawiam,';
        $lines[] = 'Waldemar Grabowski';
        $lines[] = 'NODN Platforma Nowoczesnej Edukacji';

        return implode("\n", $lines);
    }
}
