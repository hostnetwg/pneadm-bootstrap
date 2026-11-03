<?php

namespace App\Services;

use App\Models\ClosedTrainingCase;
use App\Models\Instructor;
use App\Models\TrainingOffer;

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
        $case->loadMissing(['trainingOffer.instructor', 'instructor']);

        $title = $this->proposalTitle($case);
        $format = $case->preferredFormatLabel();
        if ($format === 'Nie wybrano') {
            $format = 'do ustalenia';
        }

        $instructor = $this->proposalInstructor($case);
        $instructorLabel = $instructor
            ? trim((string) $instructor->full_title_name)
            : '';
        if ($instructorLabel === '') {
            $instructorLabel = '(uzupełnij przed wysyłką)';
        }

        $html = '<p>Dzień dobry,</p>'
            .'<p>przesyłam propozycję szkolenia dla Państwa placówki.</p>'
            .'<p><strong>Szkolenie:</strong> '.$this->e($title).'<br>'
            .'<strong>Forma:</strong> '.$this->e($format).'<br>'
            .'<strong>Cena:</strong> '.$this->e($this->proposalPriceLabel($case)).'<br>'
            .'<strong>Czas trwania:</strong> <em>(uzupełnij przed wysyłką)</em><br>'
            .'<strong>Prowadzący:</strong> '.$this->e($instructorLabel).'</p>';

        $bio = $this->instructorBio($instructor);
        if ($bio !== '') {
            $html .= '<p>'.$this->e($bio).'</p>';
        }

        $schedule = $case->listScheduleLabel();
        if ($schedule !== '—') {
            $html .= '<p>Najbliższy możliwy termin (roboczo): <strong>'.$this->e($schedule).'</strong>.</p>';
        } else {
            $html .= '<p>Termin ustalimy wspólnie.</p>';
        }

        $html .= $this->schoolConnectBlock($instructor, $shareInstructorContact, $schedule !== '—');

        $offerUrl = $this->publicOfferUrl($case->trainingOffer);
        if ($offerUrl !== null) {
            $html .= '<p>Opis szkolenia: <a href="'.$this->e($offerUrl).'">'.$this->e($offerUrl).'</a></p>';
        }

        $original = trim(strip_tags((string) $case->message));
        if ($original !== '') {
            $html .= '<hr><p><em>'.$this->e($original).'</em></p><hr>';
        }

        $html .= ClosedTrainingCase::defaultSchoolEmailBodyHtml();

        return [
            'ok' => true,
            'subject' => $case->withCaseReferenceInSubject('Propozycja szkolenia – '.$title),
            'html' => ClosedTrainingCase::sanitizeSchoolEmailHtml($html),
        ];
    }

    private function proposalTitle(ClosedTrainingCase $case): string
    {
        $offerTitle = trim((string) ($case->trainingOffer?->title ?? ''));

        return $offerTitle !== '' ? $offerTitle : $this->topicLabel($case);
    }

    private function proposalInstructor(ClosedTrainingCase $case): ?Instructor
    {
        $instructor = $this->coordinationInstructor($case);
        if ($instructor instanceof Instructor) {
            return $instructor;
        }

        $fromOffer = $case->trainingOffer?->instructor;

        return $fromOffer instanceof Instructor ? $fromOffer : null;
    }

    private function proposalPriceLabel(ClosedTrainingCase $case): string
    {
        if ($case->client_price !== null && $case->client_price !== '') {
            return $case->formatMoney($case->client_price);
        }

        $offer = $case->trainingOffer;
        if ($offer instanceof TrainingOffer
            && $offer->price_mode === TrainingOffer::PRICE_MODE_FIXED
            && $offer->price_amount !== null) {
            return $offer->formattedPrice();
        }

        return '(uzupełnij przed wysyłką)';
    }

    private function instructorBio(?Instructor $instructor): string
    {
        if (! $instructor) {
            return '';
        }

        $bio = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $instructor->bio)) ?? '');

        return $bio;
    }

    private function schoolConnectBlock(?Instructor $instructor, bool $shareInstructorContact, bool $hasSchedule): string
    {
        if ($shareInstructorContact && $instructor) {
            $name = trim((string) ($instructor->full_title_name ?? ''));
            $phone = trim((string) ($instructor->phone ?? ''));
            $intro = $hasSchedule
                ? 'Jeżeli ten termin Państwu nie odpowiada, można skontaktować się bezpośrednio z prowadzącym'
                : 'W sprawie terminu można skontaktować się bezpośrednio z prowadzącym';

            return '<p>'.$intro
                .($name !== '' ? ' '.$this->e($name) : '')
                .($phone !== '' ? ', tel. '.$this->e($phone) : '')
                .' i powołać się na Platformę Nowoczesnej Edukacji (Waldemar Grabowski). '
                .'Po ustaleniu terminu proszę o informację zwrotną do nas, żebyśmy przygotowali realizację.</p>';
        }

        return '<p>Proszę o odpowiedź na tę wiadomość — po potwierdzeniu z prowadzącym wrócimy z ostatecznym terminem.</p>';
    }

    private function publicOfferUrl(?TrainingOffer $offer): ?string
    {
        if (! $offer || ! $offer->is_active || ! $offer->show_on_pnedu) {
            return null;
        }

        $slug = trim((string) $offer->slug);
        if ($slug === '') {
            return null;
        }

        return rtrim((string) config('marketing.pnedu_public_url'), '/').'/szkolenia-rad-pedagogicznych/'.$slug;
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
