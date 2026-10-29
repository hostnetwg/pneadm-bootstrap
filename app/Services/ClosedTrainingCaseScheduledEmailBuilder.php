<?php

namespace App\Services;

use App\Models\ClosedTrainingCase;
use App\Models\Course;

/**
 * Treść e-maila „szkolenie zaplanowane” (online z linkiem / stacjonarne potwierdzenie).
 */
class ClosedTrainingCaseScheduledEmailBuilder
{
    public function __construct(
        private readonly GuestLiveLinkService $guestLiveLinks,
    ) {}

    public function canPrepare(ClosedTrainingCase $case): bool
    {
        $case->loadMissing(['course.onlineDetails', 'course.location']);
        $course = $case->course;
        if (! $course instanceof Course) {
            return false;
        }

        $type = strtolower(trim((string) $course->type));
        if ($type === 'offline') {
            return true;
        }
        if ($type === 'online') {
            return $this->hasResolvableAccessLink($course);
        }

        return false;
    }

    /**
     * @return array{ok: true, subject: string, html: string, variant: string, access_link: ?string}|array{ok: false, error: string}
     */
    public function build(ClosedTrainingCase $case): array
    {
        $case->loadMissing(['course.instructor', 'course.onlineDetails', 'course.location']);

        $course = $case->course;
        if (! $course instanceof Course) {
            return [
                'ok' => false,
                'error' => 'Najpierw powiąż sprawę ze szkoleniem, potem przygotuj e-mail z terminem.',
            ];
        }

        $type = strtolower(trim((string) $course->type));
        if ($type === 'online') {
            return $this->buildOnline($case, $course);
        }

        if ($type === 'offline') {
            return $this->buildOnsite($case, $course);
        }

        return [
            'ok' => false,
            'error' => 'Szkolenie ma nieobsługiwany typ (oczekiwane: online albo stacjonarne).',
        ];
    }

    public function hasResolvableAccessLink(Course $course): bool
    {
        $course->loadMissing('onlineDetails');
        $meetingLink = trim((string) ($course->onlineDetails?->meeting_link ?? ''));
        if ($meetingLink !== '' && filter_var($meetingLink, FILTER_VALIDATE_URL)) {
            return true;
        }

        return $this->guestLiveLinks->isEligible($course);
    }

    /**
     * @return array{ok: true, subject: string, html: string, variant: string, access_link: ?string}|array{ok: false, error: string}
     */
    private function buildOnline(ClosedTrainingCase $case, Course $course): array
    {
        $accessLink = $this->resolveAccessLink($course);
        if ($accessLink === null) {
            return [
                'ok' => false,
                'error' => 'Brak linku do szkolenia online. Uzupełnij meeting_link w ClickMeeting albo włącz gościnny link live na pnedu.pl.',
            ];
        }

        $title = $this->trainingTitle($case, $course);
        $schedule = $this->scheduleLabel($case, $course);
        $instructor = $this->instructorLabel($case);
        $safeTitle = e($title);
        $safeSchedule = e($schedule);
        $safeInstructor = e($instructor);
        $safeLink = e($accessLink);

        $html = '<p>Dzień dobry,</p>'
            .'<p>szkolenie dla Państwa placówki jest już zaplanowane.<br>'
            .'Poniżej przesyłam szczegóły oraz link, który proszę przekazać nauczycielom.</p>'
            .'<p><strong>Szkolenie:</strong> '.$safeTitle.'<br>'
            .'<strong>Termin:</strong> '.$safeSchedule.'<br>'
            .'<strong>Prowadzący:</strong> '.$safeInstructor.'</p>'
            .'<p><strong>Link do szkolenia:</strong><br>'
            .'<a href="'.$safeLink.'">'.$safeLink.'</a></p>'
            .'<p><strong>Jak dołączyć:</strong></p>'
            .'<ol>'
            .'<li>Proszę kliknąć w powyższy link.</li>'
            .'<li>Najlepiej zrobić to tuż przed rozpoczęciem szkolenia lub w jego trakcie.</li>'
            .'<li>Link można też sprawdzić wcześniej, żeby upewnić się, że działa.</li>'
            .'</ol>'
            .'<p>W razie problemów z dostępem proszę o kontakt telefoniczny: 501&nbsp;654&nbsp;274.</p>'
            .$this->signatureHtml();

        return [
            'ok' => true,
            'subject' => $case->withCaseReferenceInSubject('Szkolenie zaplanowane – termin i link'),
            'html' => ClosedTrainingCase::sanitizeSchoolEmailHtml($html),
            'variant' => 'online',
            'access_link' => $accessLink,
        ];
    }

    /**
     * @return array{ok: true, subject: string, html: string, variant: string, access_link: ?string}|array{ok: false, error: string}
     */
    private function buildOnsite(ClosedTrainingCase $case, Course $course): array
    {
        $title = $this->trainingTitle($case, $course);
        $schedule = $this->scheduleLabel($case, $course);
        $instructor = $this->instructorLabel($case);
        $place = $this->locationLabel($course);

        $safeTitle = e($title);
        $safeSchedule = e($schedule);
        $safeInstructor = e($instructor);

        $placeBlock = '';
        if ($place !== null) {
            $placeBlock = '<br><strong>Miejsce:</strong> '.e($place);
        }

        $html = '<p>Dzień dobry,</p>'
            .'<p>szkolenie dla Państwa placówki jest już zaplanowane.<br>'
            .'Poniżej przesyłam potwierdzenie ustaleń.</p>'
            .'<p><strong>Szkolenie:</strong> '.$safeTitle.'<br>'
            .'<strong>Termin:</strong> '.$safeSchedule.'<br>'
            .'<strong>Prowadzący:</strong> '.$safeInstructor
            .$placeBlock
            .'<br><strong>Forma:</strong> stacjonarnie</p>'
            .'<p>Proszę przekazać te informacje nauczycielom biorącym udział w szkoleniu.</p>'
            .'<p>W razie pytań proszę o kontakt telefoniczny: 501&nbsp;654&nbsp;274.</p>'
            .$this->signatureHtml();

        return [
            'ok' => true,
            'subject' => $case->withCaseReferenceInSubject('Szkolenie zaplanowane – potwierdzenie terminu'),
            'html' => ClosedTrainingCase::sanitizeSchoolEmailHtml($html),
            'variant' => 'onsite',
            'access_link' => null,
        ];
    }

    public function resolveAccessLink(Course $course): ?string
    {
        $course->loadMissing('onlineDetails');
        $meetingLink = trim((string) ($course->onlineDetails?->meeting_link ?? ''));
        if ($meetingLink !== '' && filter_var($meetingLink, FILTER_VALIDATE_URL)) {
            return $meetingLink;
        }

        $guest = $this->guestLiveLinks->panelState($course);
        $url = trim((string) ($guest['url'] ?? ''));

        return $url !== '' ? $url : null;
    }

    private function trainingTitle(ClosedTrainingCase $case, Course $course): string
    {
        $title = trim((string) $course->title);
        if ($title !== '') {
            return $title;
        }

        $topic = $case->trainingTopicLabel();

        return $topic === 'Nie podano' ? 'Szkolenie dla rady pedagogicznej' : $topic;
    }

    private function scheduleLabel(ClosedTrainingCase $case, Course $course): string
    {
        if (! $course->start_date) {
            return 'do uzupełnienia';
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

    private function instructorLabel(ClosedTrainingCase $case): string
    {
        $name = $case->listInstructorName();

        return $name === '—' ? 'do uzupełnienia' : $name;
    }

    private function locationLabel(Course $course): ?string
    {
        $course->loadMissing('location');
        $location = $course->location;
        if (! $location) {
            return null;
        }

        $parts = [];
        $name = trim((string) ($location->location_name ?? ''));
        $address = trim((string) ($location->address ?? ''));
        $postal = trim((string) ($location->postal_code ?? ''));
        $office = trim((string) ($location->post_office ?? ''));

        if ($name !== '') {
            $parts[] = $name;
        }
        if ($address !== '') {
            $parts[] = $address;
        }

        $cityLine = trim($postal.' '.$office);
        if ($cityLine !== '') {
            $parts[] = $cityLine;
        }

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
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
