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

        $dateLine = 'Data szkolenia: brak w systemie.';
        if ($course->start_date) {
            $dateLine = 'Data szkolenia: '.$course->start_date->format('d.m.Y H:i').'.';
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

        // Materiały
        $materialLines = [];
        foreach ($course->fileLinks->sortBy('order') as $fl) {
            $url = trim((string) ($fl->url ?? ''));
            if ($url === '') {
                continue;
            }
            $label = trim((string) ($fl->title ?? ''));
            if ($label !== '') {
                $materialLines[] = '   '.$label.': '.$url;
            } else {
                $materialLines[] = '   '.$url;
            }
        }
        if ($materialLines !== []) {
            $lines[] = "{$n}) MATERIAŁY:";
            foreach ($materialLines as $ml) {
                $lines[] = $ml;
            }
            $lines[] = '';
            $n++;
        }

        // Ankiety zewnętrzne (Formularze)
        $surveyLines = [];
        foreach ($course->surveyLinks->sortBy('order') as $sl) {
            $url = trim((string) ($sl->url ?? ''));
            if ($url === '') {
                continue;
            }
            $label = trim((string) ($sl->title ?? ''));
            $suffix = $sl->isAvailableNow() ? '' : ' (ankieta nieaktywna lub poza terminem dostępu w systemie)';
            if ($label !== '') {
                $surveyLines[] = '   '.$label.': '.$url.$suffix;
            } else {
                $surveyLines[] = '   '.$url.$suffix;
            }
        }
        if ($surveyLines !== []) {
            $lines[] = "{$n}) ANKIETA:";
            foreach ($surveyLines as $sl) {
                $lines[] = $sl;
            }
            $lines[] = '';
        }

        $lines[] = 'Pozdrawiam,';
        $lines[] = 'Waldemar Grabowski';
        $lines[] = 'NODN Platforma Nowoczesnej Edukacji';

        return implode("\n", $lines);
    }
}
