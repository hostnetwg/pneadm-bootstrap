<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateEmailLog;
use App\Models\ClosedTrainingCase;
use App\Models\Course;
use App\Models\Participant;
use Illuminate\Support\Collection;

/**
 * E-mail z podziękowaniem po zakończonym szkoleniu online: lista uczestników i jeden link dla szkoły.
 */
class ClosedTrainingCaseThankYouEmailBuilder
{
    public function canPrepare(ClosedTrainingCase $case): bool
    {
        $course = $this->onlineCourse($case);

        return $course instanceof Course && $course->hasEnded();
    }

    /**
     * @return array{ok: true, subject: string, html: string}|array{ok: false, error: string}
     */
    public function build(ClosedTrainingCase $case): array
    {
        $course = $this->onlineCourse($case);
        if (! $course instanceof Course) {
            return [
                'ok' => false,
                'error' => 'Podziękowanie z listą uczestników jest dla powiązanego szkolenia online.',
            ];
        }

        if (! $course->hasEnded()) {
            return [
                'ok' => false,
                'error' => 'E-mail z podziękowaniem będzie dostępny po zakończeniu szkolenia online.',
            ];
        }

        $token = $case->rotateSchoolCertificateToken();
        $case->refresh();
        $url = $this->publicUrl($token);
        $title = $this->trainingTitle($case, $course);
        $when = $this->scheduleLabel($course);
        $rows = $this->participantRows($course);
        $days = ClosedTrainingCase::SCHOOL_CERTIFICATE_LINK_DEFAULT_DAYS;
        $expiry = e(ClosedTrainingCase::schoolCertificateExpirySentence($days));
        $validUntil = e(ClosedTrainingCase::schoolCertificateValidUntilSentence($days));
        $safeUrl = e($url);

        $html = '<p style="margin:0;">Dzień dobry,<br><br>'
            .'dziękujemy za skorzystanie z oferty Platformy Nowoczesnej Edukacji oraz za udział Państwa placówki w szkoleniu <strong>'.e($title).'</strong>'
            .($when !== '' ? ' ('.e($when).')' : '')
            .'.<br><br>'
            .'Poniżej lista uczestników szkolenia, którzy wypełnili formularz obecności udostępniony podczas szkolenia. Przy każdej osobie widać, czy link do zaświadczenia został wysłany na jej e-mail. '
            .'Czy uczestnik już pobrał zaświadczenie, widać na stronie do pobrania — tam stan jest aktualny: <a href="'.$safeUrl.'">'.$safeUrl.'</a><br>'
            .$expiry.'<br>'
            .'<span style="color:#dc3545;">'.$validUntil.'</span></p>'
            .'<p>Po pobraniu wszystkich zaświadczeń możesz od razu unieważnić ten link wyświetlający listę uczestników. '
            .'Przycisk jest na stronie pobierania. Samo otwarcie tej wiadomości linku nie wyłącza. '
            .'<strong><a href="'.$safeUrl.'#uniewaznij-link">Unieważnij link do listy uczestników</a></strong></p>'
            .$this->participantTableHtml($rows)
            .'<p>Wydrukowane zaświadczenia można przekazać nauczycielom.</p>'
            .'<p>Jeżeli lista zawiera błędy lub nie jest kompletna, proszę o informację w odpowiedzi na tę wiadomość. Niezbędne dane to: imię, nazwisko, e-mail, data i miejsce urodzenia.</p>'
            .$this->signatureHtml();

        return [
            'ok' => true,
            'subject' => $case->withCaseReferenceInSubject('Podziękowanie za szkolenie – '.$title),
            'html' => ClosedTrainingCase::sanitizeSchoolEmailHtml($html),
        ];
    }

    public function publicUrl(string $token): string
    {
        $base = rtrim((string) config('marketing.pnedu_public_url', 'https://pnedu.pl'), '/');

        return $base.'/szkolenie-zamkniete/zaswiadczenia/'.$token;
    }

    /**
     * @return list<array{name: string, email: string, link_sent: bool, downloaded: bool}>
     */
    public function participantRows(Course $course): array
    {
        $participants = Participant::query()
            ->where('course_id', $course->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return $this->mapRows($course->id, $participants);
    }

    /**
     * @param  Collection<int, Participant>  $participants
     * @return list<array{name: string, email: string, link_sent: bool, downloaded: bool}>
     */
    private function mapRows(int $courseId, Collection $participants): array
    {
        $ids = $participants->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $sentIds = $this->sentParticipantIds($courseId, $ids);
        $downloadedIds = $this->downloadedParticipantIds($courseId, $ids);

        $rows = [];
        foreach ($participants as $participant) {
            $id = (int) $participant->id;
            $name = trim((string) $participant->first_name.' '.(string) $participant->last_name);
            $email = trim((string) $participant->email);
            $rows[] = [
                'name' => $name !== '' ? $name : 'Uczestnik',
                'email' => $email,
                'link_sent' => isset($sentIds[$id]),
                'downloaded' => isset($downloadedIds[$id]),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $participantIds
     * @return array<int, true>
     */
    private function sentParticipantIds(int $courseId, array $participantIds): array
    {
        if ($participantIds === []) {
            return [];
        }

        $ids = CertificateEmailLog::query()
            ->where('course_id', $courseId)
            ->whereIn('participant_id', $participantIds)
            ->whereIn('type', CertificateEmailLog::certificateLinkTypes())
            ->where('status', CertificateEmailLog::STATUS_SENT)
            ->pluck('participant_id');

        $sent = [];
        foreach ($ids as $id) {
            $sent[(int) $id] = true;
        }

        return $sent;
    }

    /**
     * @param  list<int>  $participantIds
     * @return array<int, true>
     */
    private function downloadedParticipantIds(int $courseId, array $participantIds): array
    {
        if ($participantIds === []) {
            return [];
        }

        $certificates = Certificate::query()
            ->where('course_id', $courseId)
            ->whereIn('participant_id', $participantIds)
            ->get(['participant_id', 'download_count', 'first_downloaded_at']);

        $downloaded = [];
        foreach ($certificates as $certificate) {
            $count = (int) ($certificate->download_count ?? 0);
            if ($count > 0 || $certificate->first_downloaded_at !== null) {
                $downloaded[(int) $certificate->participant_id] = true;
            }
        }

        return $downloaded;
    }

    /**
     * @param  list<array{name: string, email: string, link_sent: bool, downloaded: bool}>  $rows
     */
    private function participantTableHtml(array $rows): string
    {
        if ($rows === []) {
            return '<p>Na tym szkoleniu nie ma jeszcze zapisanych uczestników.</p>';
        }

        $html = '<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;">'
            .'<thead><tr>'
            .'<th align="left">Lp</th>'
            .'<th align="left">Uczestnik</th>'
            .'<th align="left">Link do zaświadczenia</th>'
            .'</tr></thead><tbody>';

        foreach ($rows as $index => $row) {
            $who = e($row['name']);
            if ($row['email'] !== '') {
                $who .= '<br>'.e($row['email']);
            }
            $html .= '<tr>'
                .'<td>'.($index + 1).'</td>'
                .'<td>'.$who.'</td>'
                .'<td>'.$this->statusCell($row['link_sent'], 'wysłany na e-mail', 'nie wysłany').'</td>'
                .'</tr>';
        }

        return $html.'</tbody></table>';
    }

    private function statusCell(bool $done, string $doneLabel, string $pendingLabel): string
    {
        if (! $done) {
            return e($pendingLabel);
        }

        return '<span style="color:#0d6efd;">'.e($doneLabel).'</span>';
    }

    private function onlineCourse(ClosedTrainingCase $case): ?Course
    {
        $case->loadMissing('course');
        $course = $case->course;
        if (! $course instanceof Course) {
            return null;
        }

        if (strtolower(trim((string) $course->type)) !== 'online') {
            return null;
        }

        return $course;
    }

    private function trainingTitle(ClosedTrainingCase $case, Course $course): string
    {
        $title = trim(html_entity_decode(strip_tags((string) $course->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($title !== '') {
            return $title;
        }

        $topic = trim((string) $case->topic);

        return $topic !== '' ? $topic : 'Szkolenie';
    }

    private function scheduleLabel(Course $course): string
    {
        if (! $course->start_date) {
            return '';
        }

        $start = $course->start_date->timezone('Europe/Warsaw');
        $label = $start->format('d.m.Y').' r., godz. '.$start->format('H:i');

        if ($course->end_date) {
            $end = $course->end_date->timezone('Europe/Warsaw');
            if ($start->isSameDay($end)) {
                if ($end->format('H:i') !== $start->format('H:i')) {
                    $label .= '–'.$end->format('H:i');
                }
            } else {
                $label .= ' – '.$end->format('d.m.Y').' r., godz. '.$end->format('H:i');
            }
        }

        return $label;
    }

    private function signatureHtml(): string
    {
        return '<p>Pozdrawiam<br>'
            .'Waldemar Grabowski<br>'
            .'Akredytowany Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>'
            .'„Platforma Nowoczesnej Edukacji”<br>'
            .'tel. 501&nbsp;654&nbsp;274<br>'
            .'e-mail: <a href="mailto:kontakt@pnedu.pl">kontakt@pnedu.pl</a></p>';
    }
}
