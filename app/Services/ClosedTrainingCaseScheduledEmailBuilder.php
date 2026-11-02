<?php

namespace App\Services;

use App\Models\ClosedTrainingCase;
use App\Models\Course;
use App\Models\Instructor;
use App\Support\CourseCalendarEventBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

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

    /**
     * Szczegóły już zaplanowanego szkolenia dla prowadzącego. Z wynagrodzeniem, bez ceny szkoły.
     * Kontakt placówki tylko gdy $includeSchoolContact.
     *
     * @return array{ok: true, subject: string, html: string}|array{ok: false, error: string}
     */
    public function buildInstructorDetails(ClosedTrainingCase $case, bool $includeSchoolContact = true): array
    {
        $case->loadMissing(['course.instructor', 'course.onlineDetails', 'course.location', 'instructor']);
        $course = $case->course;
        if (! $course instanceof Course) {
            return [
                'ok' => false,
                'error' => 'Najpierw powiąż sprawę ze szkoleniem, potem przygotuj e-mail ze szczegółami.',
            ];
        }

        $instructor = $course->instructor ?? $case->instructor;
        $email = $instructor instanceof Instructor
            ? strtolower(trim((string) $instructor->email))
            : '';
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'error' => 'Brak e-maila prowadzącego. Przypisz instruktora ze skrzynką w powiązanym szkoleniu albo w ustaleniach.',
            ];
        }

        $type = strtolower(trim((string) $course->type));
        if (! in_array($type, ['online', 'offline'], true)) {
            return [
                'ok' => false,
                'error' => 'Szkolenie ma nieobsługiwany typ (oczekiwane: online albo stacjonarne).',
            ];
        }

        $isOnline = $type === 'online';
        if ($isOnline) {
            $this->refreshClickMeetingLinks($course);
        }
        $instructorLink = $isOnline ? $this->instructorMeetingLink($course) : null;
        $title = $this->trainingTitle($case, $course);
        $greetingName = trim((string) ($instructor->first_name ?? ''));
        $hello = $greetingName !== '' ? 'Dzień dobry, '.e($greetingName).',' : 'Dzień dobry,';
        $fee = $case->formatInstructorFeeList();
        $feeLabel = $fee === '—' ? '(uzupełnij przed wysyłką)' : $fee;

        $lines = [
            '<strong>Szkolenie:</strong> '.e($title),
            '<strong>Termin:</strong> '.e($this->scheduleLabel($case, $course)).$this->calendarLinksHtml($course),
            '<strong>Wynagrodzenie:</strong> '.e($feeLabel),
        ];
        $institution = trim((string) $case->institution);
        if ($institution !== '') {
            $lines[] = '<strong>Placówka:</strong> '.e($institution);
        }
        if ($includeSchoolContact && $isOnline) {
            $lines[] = $this->schoolContactInner($case);
        }
        if ($isOnline) {
            $intro = 'szkolenie online jest już zaplanowane. Poniżej przesyłam ustalenia oraz link do spotkania.';
            $closing = 'Proszę wejść w link przed rozpoczęciem, żeby sprawdzić połączenie. W razie problemów z dostępem proszę o kontakt telefoniczny: 501&nbsp;654&nbsp;274.';
            $subject = 'Szkolenie online – szczegóły – '.$title;
        } else {
            $place = $this->locationLabel($course);
            $lines[] = '<strong>Miejsce:</strong> '.e($place ?? '(uzupełnij przed wysyłką)');
            $intro = 'szkolenie stacjonarne jest już zaplanowane. Poniżej przesyłam miejsce i ustalenia.';
            $closing = 'W razie pytań proszę o kontakt telefoniczny: 501&nbsp;654&nbsp;274.';
            $subject = 'Szkolenie stacjonarne – szczegóły – '.$title;
        }

        $html = '<p style="margin:0;">'.$hello.'<br><br>'
            .$intro.'<br><br></p>'
            .'<p>'.implode('<br>', $lines).'</p>';

        if ($isOnline && is_array($instructorLink)) {
            $label = e((string) $instructorLink['label']);
            $url = $instructorLink['url'];
            $platform = $this->onlinePlatformLabel($course);
            $platformHtml = $platform !== null
                ? '<strong>Platforma:</strong> '.e($platform).'<br>'
                : '';
            if (is_string($url) && $url !== '') {
                $safeLink = e($url);
                $html .= '<p>'.$platformHtml.'<strong>'.$label.':</strong><br><a href="'.$safeLink.'">'.$safeLink.'</a></p>';
            } else {
                $html .= '<p>'.$platformHtml.'<strong>'.$label.':</strong> ('.e((string) $instructorLink['empty']).')</p>';
            }
        }

        if ($includeSchoolContact && ! $isOnline) {
            $html .= '<p>'.$this->schoolContactInner($case).'</p>';
        }

        $html .= '<p>'.$closing.'</p>'
            .$this->signatureHtml();

        return [
            'ok' => true,
            'subject' => $case->withCaseReferenceInSubject($subject),
            'html' => ClosedTrainingCase::sanitizeSchoolEmailHtml($html),
        ];
    }

    public function hasActivePriceVariant(Course $course): bool
    {
        return $this->activePriceVariants($course)->isNotEmpty();
    }

    public function orderFormNoticeHtml(Course $course): string
    {
        $variants = $this->activePriceVariants($course);
        $variantId = $variants->count() === 1 ? (int) $variants->first()->id : null;
        $url = CourseFormOrderBillingService::newOrderFormUrl($course, $variantId);
        $safeUrl = e($url);

        return '<strong>W celu oficjalnego potwierdzenia terminu proszę wypełnić formularz zamówienia:</strong><br>'
            .'<a href="'.$safeUrl.'">'.$safeUrl.'</a><br>'
            .'Jako uczestnika proszę wpisać imię, nazwisko i adres e-mail dyrektora. Pozostali nauczyciele podczas szkolenia otrzymają do wypełnienia formularz listy obecności, na podstawie której wystawimy zaświadczenia.<br>'
            .'Faktura z 14-dniowym terminem płatności zostanie wystawiona w dniu szkolenia.';
    }

    public function insertOrderFormNotice(string $html, string $block): string
    {
        if (str_contains($html, 'W celu oficjalnego potwierdzenia terminu proszę wypełnić formularz zamówienia:')) {
            return $html;
        }

        $pattern = '/szkolenie(?:\s|&nbsp;|\x{00A0})+dla(?:\s|&nbsp;|\x{00A0})+Państwa(?:\s|&nbsp;|\x{00A0})+placówki(?:\s|&nbsp;|\x{00A0})+jest(?:\s|&nbsp;|\x{00A0})+już(?:\s|&nbsp;|\x{00A0})+zaplanowane\./iu';
        if (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return $html;
        }

        $end = $matches[0][1] + strlen($matches[0][0]);

        return substr($html, 0, $end).'<br><br>'.$block.substr($html, $end);
    }

    public function removeOrderFormNotice(string $html): string
    {
        return preg_replace(
            '/(?:<br\s*\/?>\s*){0,2}(?:<strong>\s*)?W celu oficjalnego potwierdzenia terminu proszę wypełnić formularz zamówienia:(?:<\/strong>)?.*?zostanie wystawiona w dniu szkolenia\.\s*/isu',
            '',
            $html,
            1
        ) ?? $html;
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
            .'<strong>Termin:</strong> '.$safeSchedule.$this->calendarLinksHtml($course).'<br>'
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

        $html = '<p style="margin:0;">Dzień dobry,<br><br>'
            .'szkolenie dla Państwa placówki jest już zaplanowane.<br><br>'
            .'<strong>Poniżej przesyłam potwierdzenie ustaleń.</strong><br><br></p>'
            .'<p><strong>Szkolenie:</strong> '.$safeTitle.'<br>'
            .'<strong>Termin:</strong> '.$safeSchedule.$this->calendarLinksHtml($course).'<br>'
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

    private function calendarLinksHtml(Course $course): string
    {
        if (! $course->start_date || ! $course->exists) {
            return '';
        }

        $google = CourseCalendarEventBuilder::for($course)->caseEmailGoogleCalendarUrl();
        $apple = URL::signedRoute('courses.calendar-ics', ['course' => $course]);
        $links = [];
        if (is_string($google) && $google !== '') {
            $links[] = '<a href="'.$google.'">Dodaj do kalendarza Google</a>';
        }
        $links[] = '<a href="'.$apple.'">Dodaj do kalendarza iCalendar Apple</a>';

        return '<br>'.implode(' · ', $links);
    }

    private function onlinePlatformLabel(Course $course): ?string
    {
        $course->loadMissing('onlineDetails');
        $platform = trim((string) ($course->onlineDetails?->platform ?? ''));
        if ($platform !== '') {
            return $platform;
        }

        $eventId = trim((string) ($course->onlineDetails?->clickmeeting_event_id ?? ''));

        return $eventId !== '' ? 'ClickMeeting' : null;
    }

    /**
     * @return array{url: ?string, label: string, empty: string}
     */
    private function instructorMeetingLink(Course $course): array
    {
        $course->loadMissing('onlineDetails');
        $eventId = trim((string) ($course->onlineDetails?->clickmeeting_event_id ?? ''));
        if ($eventId !== '') {
            $presenter = trim((string) ($course->onlineDetails?->presenter_meeting_link ?? ''));
            $url = $presenter !== '' && filter_var($presenter, FILTER_VALIDATE_URL) ? $presenter : null;

            return [
                'url' => $url,
                'label' => 'Link dla prowadzącego',
                'empty' => 'brak linku prezentera — uzupełnij przed wysyłką',
            ];
        }

        return [
            'url' => $this->resolveAccessLink($course),
            'label' => 'Link do szkolenia',
            'empty' => 'brak linku — uzupełnij przed wysyłką',
        ];
    }

    private function refreshClickMeetingLinks(Course $course): void
    {
        $course->loadMissing('onlineDetails');
        $eventId = trim((string) ($course->onlineDetails?->clickmeeting_event_id ?? ''));
        if ($eventId === '') {
            return;
        }

        app(ClickMeetingCourseRoomUrlSyncService::class)->refreshFromApi($course);
        $course->unsetRelation('onlineDetails');
        $course->load('onlineDetails');
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

    /**
     * @return Collection<int, \App\Models\CoursePriceVariant>
     */
    private function activePriceVariants(Course $course): Collection
    {
        if ($course->relationLoaded('activePriceVariants')) {
            return $course->activePriceVariants;
        }

        return $course->activePriceVariants()->get();
    }

    private function schoolContactInner(ClosedTrainingCase $case): string
    {
        $lines = [];
        $name = trim((string) $case->contact_name);
        if ($name !== '') {
            $lines[] = '<strong>Osoba:</strong> '.e($name);
        }
        $phone = trim((string) $case->contact_phone);
        if ($phone !== '') {
            $lines[] = '<strong>Telefon:</strong> '.e($phone);
        }
        $emails = $case->contactEmailList();
        if ($emails !== []) {
            $lines[] = '<strong>E-mail:</strong> '.e(implode(', ', $emails));
        }

        if ($lines === []) {
            return '<strong>Kontakt:</strong> (brak danych na sprawie — uzupełnij przed wysyłką)';
        }

        return '<strong>Kontakt:</strong><br>'.implode('<br>', $lines);
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
