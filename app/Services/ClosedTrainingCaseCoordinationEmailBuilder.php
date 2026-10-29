<?php

namespace App\Services;

use App\Models\ClosedTrainingCase;
use App\Models\Instructor;

/**
 * Maile koordynacji terminu: propozycja dla szkoły oraz bezpieczny brief dla prowadzącego.
 */
class ClosedTrainingCaseCoordinationEmailBuilder
{
    public function coordinationInstructor(ClosedTrainingCase $case): ?Instructor
    {
        $case->loadMissing(['course.instructor', 'instructor']);

        $instructor = $case->course?->instructor ?? $case->instructor;

        return $instructor instanceof Instructor ? $instructor : null;
    }

    public function instructorEmail(ClosedTrainingCase $case): ?string
    {
        $instructor = $this->coordinationInstructor($case);
        if (! $instructor) {
            return null;
        }

        $email = strtolower(trim((string) $instructor->email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function canEmailInstructor(ClosedTrainingCase $case): bool
    {
        return $this->instructorEmail($case) !== null;
    }

    /**
     * @return array{ok: true, subject: string, html: string}|array{ok: false, error: string}
     */
    public function buildInstructorBrief(ClosedTrainingCase $case, bool $shareSchoolContact): array
    {
        if (! $this->canEmailInstructor($case)) {
            return [
                'ok' => false,
                'error' => 'Brak e-maila prowadzącego. Przypisz instruktora ze skrzynką w ustaleniach albo w powiązanym szkoleniu.',
            ];
        }

        $instructor = $this->coordinationInstructor($case);
        $topic = $this->topicLabel($case);
        $format = $case->preferredFormatLabel();
        $schedule = $case->listScheduleLabel();
        if ($schedule === '—') {
            $schedule = 'do ustalenia';
        }

        $greetingName = trim((string) ($instructor?->first_name ?? ''));
        $hello = $greetingName !== '' ? 'Dzień dobry, '.$this->e($greetingName).',' : 'Dzień dobry,';

        $html = '<p>'.$hello.'</p>'
            .'<p>w sprawie <strong>'.$this->e($case->referenceCode()).'</strong> proszę o potwierdzenie dostępności albo propozycję innego terminu.</p>'
            .'<p><strong>Temat:</strong> '.$this->e($topic).'<br>'
            .'<strong>Forma:</strong> '.$this->e($format).'<br>'
            .'<strong>Propozycja terminu (ze sprawy):</strong> '.$this->e($schedule).'</p>';

        if ($shareSchoolContact) {
            $html .= $this->schoolContactBlock($case);
            $html .= '<p>Możesz skontaktować się ze szkołą bezpośrednio w sprawie terminu. '
                .'Proszę potem dać znać na kontakt@pnedu.pl, co ustaliliście, żebyśmy przygotowali platformę / organizację.</p>';
        } else {
            $html .= '<p>Proszę <strong>nie kontaktować się bezpośrednio ze szkołą</strong> w tej sprawie — '
                .'odpisz na tę wiadomość (Reply-To: kontakt@pnedu.pl), a my wrócimy do placówki.</p>';
        }

        $html .= '<p><strong>Ważne:</strong> warunków finansowych i ceny dla szkoły nie ustalamy poza PNE. '
            .'Jeśli szkoła zapyta o kwotę, proszę odesłać ją do nas.</p>'
            .$this->signatureHtml();

        return [
            'ok' => true,
            'subject' => $case->withCaseReferenceInSubject('Prośba o termin – '.$topic),
            'html' => ClosedTrainingCase::sanitizeSchoolEmailHtml($html),
        ];
    }

    /**
     * Prefill propozycji do szkoły (z ceną; opcjonalnie z kontaktem do prowadzącego).
     *
     * @return array{ok: true, subject: string, html: string}|array{ok: false, error: string}
     */
    public function buildSchoolProposal(ClosedTrainingCase $case, bool $shareInstructorContact): array
    {
        $topic = $this->topicLabel($case);
        $schedule = $case->listScheduleLabel();
        if ($schedule === '—') {
            $schedule = 'do ustalenia';
        }

        $priceLine = '';
        if ($case->client_price !== null && $case->client_price !== '') {
            $priceLine = '<p>Koszt szkolenia online/stacjonarnego: <strong>'
                .$this->e($case->formatMoneyCompact($case->client_price))
                .'</strong>.</p>';
        } else {
            $priceLine = '<p>Koszt szkolenia: <em>(uzupełnij kwotę brutto)</em>.</p>';
        }

        $original = trim(strip_tags((string) $case->message));
        $quote = $original !== ''
            ? '<hr><p><em>'.$this->e($original).'</em></p><hr>'
            : '';

        $instructor = $this->coordinationInstructor($case);
        $connectBlock = '';
        if ($shareInstructorContact && $instructor) {
            $name = trim((string) ($instructor->full_title_name ?? ''));
            $phone = trim((string) ($instructor->phone ?? ''));
            $connectBlock = '<p>Jeżeli ten termin Państwu nie odpowiada, można skontaktować się bezpośrednio z prowadzącym'
                .($name !== '' ? ' '.$this->e($name) : '')
                .($phone !== '' ? ', tel. '.$this->e($phone) : '')
                .' i powołać się na Platformę Nowoczesnej Edukacji (Waldemar Grabowski). '
                .'Po ustaleniu terminu proszę o informację zwrotną do nas, żebyśmy przygotowali realizację.</p>';
        } else {
            $connectBlock = '<p>Proszę o odpowiedź na tę wiadomość — po potwierdzeniu z prowadzącym wrócimy z ostatecznym terminem.</p>';
        }

        $html = '<p>Dzień dobry,</p>'
            .'<p>możemy takie szkolenie dla Państwa zorganizować. '
            .'Najbliższy możliwy termin (roboczo): <strong>'.$this->e($schedule).'</strong>.</p>'
            .$priceLine
            .$connectBlock
            .$quote
            .ClosedTrainingCase::defaultSchoolEmailBodyHtml();

        return [
            'ok' => true,
            'subject' => $case->withCaseReferenceInSubject('Propozycja terminu – '.$topic),
            'html' => ClosedTrainingCase::sanitizeSchoolEmailHtml($html),
        ];
    }

    private function schoolContactBlock(ClosedTrainingCase $case): string
    {
        $lines = [];
        $name = trim((string) $case->contact_name);
        if ($name !== '') {
            $lines[] = '<strong>Osoba:</strong> '.$this->e($name);
        }
        $phone = trim((string) $case->contact_phone);
        if ($phone !== '') {
            $lines[] = '<strong>Telefon:</strong> '.$this->e($phone);
        }
        $emails = $case->contactEmailList();
        if ($emails !== []) {
            $lines[] = '<strong>E-mail:</strong> '.$this->e(implode(', ', $emails));
        }
        $institution = trim((string) $case->institution);
        if ($institution !== '') {
            $lines[] = '<strong>Placówka:</strong> '.$this->e($institution);
        }

        if ($lines === []) {
            return '<p><strong>Kontakt szkoły:</strong> (brak uzupełnionych danych na sprawie)</p>';
        }

        return '<p><strong>Kontakt szkoły:</strong><br>'.implode('<br>', $lines).'</p>';
    }

    private function topicLabel(ClosedTrainingCase $case): string
    {
        $topic = $case->trainingTopicLabel();

        return $topic === 'Nie podano' ? 'Szkolenie dla rady pedagogicznej' : $topic;
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

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
