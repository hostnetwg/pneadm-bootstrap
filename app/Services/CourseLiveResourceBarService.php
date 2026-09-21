<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Models\CourseSurveyLink;
use App\Models\Participant;
use App\Models\ParticipantLiveAccess;
use Illuminate\Support\Facades\Schema;

/**
 * Stan belki zasobów na osadzonej transmisji: flagi ADM + URL-e, które realnie pójdą do uczestnika.
 */
class CourseLiveResourceBarService
{
    public const MAX_MATERIAL_LINKS = 5;

    public const LABEL_ATTENDANCE = 'Rejestracja: lista obecności';

    public const LABEL_CERTIFICATE = 'Pobierz zaświadczenie';

    public const LABEL_MATERIALS = 'Pobierz materiały';

    public const LABEL_SURVEY = 'Wypełnij ankietę';

    public const CHAT_ATTENDANCE = 'LISTA OBECNOŚCI';

    public const CHAT_MATERIALS = 'MATERIAŁY';

    public const CHAT_SURVEY = 'ANKIETA';

    public const CHAT_CERTIFICATE = 'ZAŚWIADCZENIE';

    public const CHAT_OFFER = 'SZKOLENIE';

    public const CHAT_OFFER_TITLE_MAX = 40;

    /**
     * Osadzony /transmisja i gość /live/{token}: rejestracji na belce nie ma.
     * Gość podaje imię, nazwisko i e-mail na formularzu przed wejściem.
     */
    public const ATTENDANCE_VISIBLE_ON_AUTHENTICATED_EMBED = false;

    public const ONLINE_WINDOW_SECONDS = 180;

    /** Oferta na live: po tylu sekundach znika sama (uczestnik + przełącznik ADM), chyba że operator ukryje wcześniej. */
    public const LIVE_OFFER_AUTO_HIDE_SECONDS = 120;

    public const ONLINE_LIST_LIMIT = 100;

    public const PANEL_POLL_MS = 5000;

    public function __construct(
        private readonly GuestLiveLinkService $guestLive,
    ) {}

    /**
     * @return array{
     *     flags: array{attendance: bool, certificate: bool, materials: bool, survey: bool},
     *     offer: array{enabled: bool, course_id: ?int, course: ?array<string, mixed>},
     *     resources: array{
     *         attendance: array{ready: bool, parked: bool, url: ?string},
     *         certificate: array{ready: bool, url: ?string},
     *         materials: array{ready: bool, items: list<array{id: int, label: string, url: string}>},
     *         survey: array{ready: bool, items: list<array{id: int, label: string, url: string}>}
     *     },
     *     links: list<array{key: string, label: string, url: string}>,
     *     embed_on_pnedu: bool,
     *     has_online_details: bool,
     *     embed_entries: array{ever: int, recent_15min: int},
     *     online_now: array{count: int, viewers: list<array{id: int, name: string, email: string}>, truncated: bool, window_seconds: int},
     *     guest_live: array{eligible: bool, url: ?string, hint: string},
     *     cm_chat: array{text: string, empty: bool, lines: list<array{label: string, url: string, text: string}>}
     * }
     */
    public function panelState(Course $course): array
    {
        $course->loadMissing(['onlineDetails', 'fileLinks', 'surveyLinks']);
        $details = $course->onlineDetails;
        $materials = $this->materialItems($course);
        $surveys = $this->surveyItems($course);
        $attendanceUrl = $this->attendanceUrl($course);
        $certificateUrl = $this->certificateDownloadUrl($course);
        $guestLive = $this->guestLive->panelState($course);
        $guestEligible = (bool) ($guestLive['eligible'] ?? false);
        $offer = $this->offerState($course, $details);

        return [
            'flags' => [
                'attendance' => (bool) ($details?->live_bar_attendance_enabled),
                'certificate' => (bool) ($details?->live_bar_certificate_enabled),
                'materials' => (bool) ($details?->live_bar_materials_enabled),
                'survey' => (bool) ($details?->live_bar_survey_enabled),
            ],
            'offer' => $offer,
            'resources' => [
                'attendance' => [
                    'ready' => false,
                    'parked' => true,
                    'url' => $attendanceUrl,
                ],
                'certificate' => [
                    'ready' => $certificateUrl !== null,
                    'url' => $certificateUrl,
                ],
                'materials' => [
                    'ready' => $materials !== [],
                    'items' => $materials,
                ],
                'survey' => [
                    'ready' => $surveys !== [],
                    'items' => $surveys,
                ],
            ],
            'links' => $guestEligible ? $this->visibleLinksForGuest($course) : $this->visibleLinks($course),
            'embed_on_pnedu' => (bool) ($details?->embed_on_pnedu),
            'has_online_details' => $details !== null,
            'embed_entries' => $this->embedEntryCounts($course),
            'online_now' => $this->onlineNow($course),
            'guest_live' => $guestLive,
            'cm_chat' => $this->clickMeetingChat($course, $offer),
        ];
    }

    /**
     * Tekst do wklejenia na czat ClickMeeting (osoby poza osadzonym `/transmisja`).
     * Te same włączone zasoby co belka + oferta; nie wymaga `embed_on_pnedu`.
     *
     * @param  array{enabled: bool, course_id: ?int, course: ?array<string, mixed>}|null  $offer
     * @return array{text: string, empty: bool, lines: list<array{label: string, url: string, text: string}>}
     */
    public function clickMeetingChat(Course $course, ?array $offer = null): array
    {
        $course->loadMissing(['onlineDetails', 'fileLinks', 'surveyLinks']);
        $details = $course->onlineDetails;
        $lines = [];

        $attendanceUrl = $this->attendanceUrl($course);
        if ($attendanceUrl !== null) {
            $lines[] = $this->chatLine(self::CHAT_ATTENDANCE, $attendanceUrl);
        }

        if ($details instanceof CourseOnlineDetails) {
            if ($details->live_bar_materials_enabled) {
                foreach ($this->materialItems($course) as $item) {
                    $lines[] = $this->chatLine(self::CHAT_MATERIALS, $item['url']);
                }
            }

            if ($details->live_bar_survey_enabled) {
                foreach ($this->surveyItems($course) as $item) {
                    $lines[] = $this->chatLine(self::CHAT_SURVEY, $item['url']);
                }
            }

            if ($details->live_bar_certificate_enabled) {
                $url = $this->certificateDownloadUrl($course);
                if ($url !== null) {
                    $lines[] = $this->chatLine(self::CHAT_CERTIFICATE, $url);
                }
            }
        }

        $offer ??= $this->offerState($course, $details instanceof CourseOnlineDetails ? $details : null);
        if (($offer['enabled'] ?? false) && (int) ($offer['course_id'] ?? 0) > 0) {
            $offerUrl = $this->publicCourseUrl((int) $offer['course_id']);
            if ($offerUrl !== null) {
                $title = trim((string) ($offer['course']['title_text'] ?? ''));
                $lines[] = $this->chatLine(self::CHAT_OFFER, $offerUrl, $this->offerTitleSnippet($title));
            }
        }

        $parts = array_map(static fn (array $line): string => $line['text'], $lines);
        $text = implode("\n", $parts);

        return [
            'text' => $text,
            'empty' => $text === '',
            'lines' => $lines,
        ];
    }

    /**
     * @return array{label: string, url: string, text: string}
     */
    private function chatLine(string $label, string $url, ?string $titleSnippet = null): array
    {
        $text = ($titleSnippet !== null && $titleSnippet !== '')
            ? $label.': '.$titleSnippet.' ... '.$url
            : $label.': '.$url;

        return [
            'label' => $label,
            'url' => $url,
            'text' => $text,
        ];
    }

    private function offerTitleSnippet(string $title): ?string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        if ($title === '') {
            return null;
        }

        if (mb_strlen($title) <= self::CHAT_OFFER_TITLE_MAX) {
            return $title;
        }

        return trim(mb_substr($title, 0, self::CHAT_OFFER_TITLE_MAX));
    }

    public function publicCourseUrl(int $courseId): ?string
    {
        if ($courseId <= 0) {
            return null;
        }

        $base = rtrim((string) config('services.pnedu_frontend_url', ''), '/');
        if ($base === '') {
            return null;
        }

        return $base.'/courses/'.$courseId;
    }

    /**
     * Linki, które belka na /transmisja ma pokazać teraz (flaga ON i zasób istnieje).
     *
     * @return list<array{key: string, label: string, url: string}>
     */
    public function visibleLinks(Course $course): array
    {
        $course->loadMissing(['onlineDetails', 'fileLinks', 'surveyLinks']);
        $details = $course->onlineDetails;
        if (! $details instanceof CourseOnlineDetails || ! $details->embed_on_pnedu) {
            return [];
        }

        $links = [];

        if (self::ATTENDANCE_VISIBLE_ON_AUTHENTICATED_EMBED && $details->live_bar_attendance_enabled) {
            $url = $this->attendanceUrl($course);
            if ($url !== null) {
                $links[] = [
                    'key' => 'attendance',
                    'label' => self::LABEL_ATTENDANCE,
                    'url' => $url,
                ];
            }
        }

        if ($details->live_bar_materials_enabled) {
            foreach ($this->materialItems($course) as $item) {
                $links[] = [
                    'key' => 'material-'.$item['id'],
                    'label' => self::LABEL_MATERIALS,
                    'url' => $item['url'],
                ];
            }
        }

        if ($details->live_bar_survey_enabled) {
            foreach ($this->surveyItems($course) as $item) {
                $links[] = [
                    'key' => 'survey-'.$item['id'],
                    'label' => self::LABEL_SURVEY,
                    'url' => $item['url'],
                ];
            }
        }

        if ($details->live_bar_certificate_enabled) {
            $url = $this->certificateDownloadUrl($course);
            if ($url !== null) {
                $links[] = [
                    'key' => 'certificate',
                    'label' => self::LABEL_CERTIFICATE,
                    'url' => $url,
                ];
            }
        }

        return $links;
    }

    /**
     * Belka gościa `/live/{token}`: materiały + ankieta; bez rejestracji (formularz przed wejściem) i bez zaświadczenia.
     *
     * @return list<array{key: string, label: string, url: string}>
     */
    public function visibleLinksForGuest(Course $course): array
    {
        $course->loadMissing(['onlineDetails', 'fileLinks', 'surveyLinks']);
        $details = $course->onlineDetails;
        if (! $details instanceof CourseOnlineDetails || ! $details->embed_on_pnedu) {
            return [];
        }

        $links = [];

        if ($details->live_bar_materials_enabled) {
            foreach ($this->materialItems($course) as $item) {
                $links[] = [
                    'key' => 'material-'.$item['id'],
                    'label' => self::LABEL_MATERIALS,
                    'url' => $item['url'],
                ];
            }
        }

        if ($details->live_bar_survey_enabled) {
            foreach ($this->surveyItems($course) as $item) {
                $links[] = [
                    'key' => 'survey-'.$item['id'],
                    'label' => self::LABEL_SURVEY,
                    'url' => $item['url'],
                ];
            }
        }

        return $links;
    }

    /**
     * @return array{
     *   enabled: bool,
     *   course_id: ?int,
     *   course: ?array<string, mixed>,
     *   auto_hide: bool,
     *   enabled_at: ?string,
     *   expires_at: ?string,
     *   auto_hide_seconds: int
     * }
     */
    public function offerState(Course $liveCourse, ?CourseOnlineDetails $details): array
    {
        $empty = [
            'enabled' => false,
            'course_id' => null,
            'course' => null,
            'auto_hide' => true,
            'enabled_at' => null,
            'expires_at' => null,
            'auto_hide_seconds' => self::LIVE_OFFER_AUTO_HIDE_SECONDS,
        ];

        if ($details instanceof CourseOnlineDetails) {
            $this->expireLiveOfferIfNeeded($details);
            $details->refresh();
        }

        $offerId = (int) ($details?->live_offer_course_id ?? 0);
        if ($offerId <= 0 || $offerId === (int) $liveCourse->id) {
            if ($details instanceof CourseOnlineDetails) {
                $empty['auto_hide'] = $details->live_offer_auto_hide !== false;
            }

            return $empty;
        }

        $offerCourse = Course::query()
            ->with('instructor:id,title,first_name,last_name')
            ->find($offerId);

        if (! $offerCourse instanceof Course) {
            return $empty;
        }

        $enabled = (bool) ($details?->live_offer_enabled);
        $autoHide = $details?->live_offer_auto_hide !== false;
        $enabledAt = $details?->live_offer_enabled_at;
        $expiresAt = null;
        if ($enabled && $autoHide && $enabledAt) {
            $expiresAt = $enabledAt->copy()->addSeconds(self::LIVE_OFFER_AUTO_HIDE_SECONDS);
        }

        return [
            'enabled' => $enabled,
            'course_id' => (int) $offerCourse->id,
            'course' => $this->adminSelectItem($offerCourse),
            'auto_hide' => $autoHide,
            'enabled_at' => $enabled && $autoHide && $enabledAt ? $enabledAt->toIso8601String() : null,
            'expires_at' => $expiresAt?->toIso8601String(),
            'auto_hide_seconds' => self::LIVE_OFFER_AUTO_HIDE_SECONDS,
        ];
    }

    /**
     * Wyłącza ofertę po LIVE_OFFER_AUTO_HIDE_SECONDS tylko gdy live_offer_auto_hide = true.
     */
    public function expireLiveOfferIfNeeded(CourseOnlineDetails $details): bool
    {
        if (! $details->live_offer_enabled) {
            if ($details->live_offer_enabled_at !== null) {
                $details->live_offer_enabled_at = null;
                $details->save();

                return true;
            }

            return false;
        }

        if ($details->live_offer_auto_hide === false) {
            if ($details->live_offer_enabled_at !== null) {
                $details->live_offer_enabled_at = null;
                $details->save();

                return true;
            }

            return false;
        }

        if ($details->live_offer_enabled_at === null) {
            $details->live_offer_enabled_at = now();
            $details->save();

            return true;
        }

        $deadline = $details->live_offer_enabled_at->copy()
            ->addSeconds(self::LIVE_OFFER_AUTO_HIDE_SECONDS);
        if (now()->lt($deadline)) {
            return false;
        }

        $details->live_offer_enabled = false;
        $details->live_offer_enabled_at = null;
        $details->save();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function adminSelectItem(Course $course): array
    {
        $tz = (string) config('app.timezone');
        $instructor = $course->instructor;
        $instructorName = '';
        if ($instructor) {
            $instructorName = trim(($instructor->title ? $instructor->title.' ' : '').$instructor->first_name.' '.$instructor->last_name);
        }

        return [
            'value' => (string) $course->id,
            'id' => (int) $course->id,
            'id_hash' => '#'.$course->id,
            'id_old' => (string) ($course->id_old ?? ''),
            'title_text' => $course->plainTitle(''),
            'title_html' => (string) $course->title,
            'start_date' => $course->start_date ? $course->start_date->copy()->timezone($tz)->format('Y-m-d H:i') : null,
            'end_date' => $course->end_date ? $course->end_date->copy()->timezone($tz)->format('Y-m-d H:i') : null,
            'status' => $course->getLifecycleStatus(),
            'instructor' => $instructorName,
        ];
    }

    public function attendanceUrl(Course $course): ?string
    {
        if (! $course->isCertificateRegistrationOpenForExtendedAccess()) {
            return null;
        }

        $url = $course->certificateRegistrationPublicUrl();

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function certificateDownloadUrl(Course $course): ?string
    {
        if (($course->certificate_download_status ?? '') !== 'download_enabled') {
            return null;
        }

        $base = rtrim((string) config('services.pnedu_frontend_url', ''), '/');
        if ($base === '') {
            return null;
        }

        return $base.'/dashboard/zaswiadczenia/'.$course->id;
    }

    /**
     * @return list<array{id: int, label: string, url: string}>
     */
    public function materialItems(Course $course): array
    {
        $items = [];
        foreach ($course->fileLinks as $link) {
            $url = trim((string) ($link->url ?? ''));
            if ($url === '') {
                continue;
            }
            $title = trim((string) ($link->title ?? ''));
            $items[] = [
                'id' => (int) $link->id,
                'label' => $title !== '' ? $title : 'Materiały',
                'url' => $url,
            ];
            if (count($items) >= self::MAX_MATERIAL_LINKS) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return list<array{id: int, label: string, url: string}>
     */
    public function surveyItems(Course $course): array
    {
        $items = [];
        $links = $course->relationLoaded('surveyLinks')
            ? $course->surveyLinks
            : $course->surveyLinks()->get();

        foreach ($links as $link) {
            if (! $link instanceof CourseSurveyLink || ! $link->isAvailableNow()) {
                continue;
            }
            $url = trim((string) $link->participantFacingSurveyUrl());
            if ($url === '') {
                continue;
            }
            $title = trim((string) ($link->title ?? ''));
            $items[] = [
                'id' => (int) $link->id,
                'label' => $title !== '' ? $title : 'Ankieta',
                'url' => $url,
            ];
        }

        return $items;
    }

    /**
     * @return array{ever: int, recent_15min: int}
     */
    public function embedEntryCounts(Course $course): array
    {
        $base = ParticipantLiveAccess::query()
            ->where('course_id', $course->id)
            ->whereNotNull('embed_last_entered_at');

        return [
            'ever' => (int) (clone $base)->count(),
            'recent_15min' => (int) (clone $base)
                ->where('embed_last_entered_at', '>=', now()->subMinutes(15))
                ->count(),
        ];
    }

    /**
     * Osoby z aktywnym heartbeatem na /transmisja (okno ~TTL slotu obecności).
     *
     * @return array{count: int, viewers: list<array{id: int, name: string, email: string}>, truncated: bool, window_seconds: int}
     */
    public function onlineNow(Course $course): array
    {
        $empty = [
            'count' => 0,
            'viewers' => [],
            'truncated' => false,
            'window_seconds' => self::ONLINE_WINDOW_SECONDS,
        ];

        try {
            if (! Schema::hasColumn('participant_live_access', 'embed_last_seen_at')) {
                return $empty;
            }
        } catch (\Throwable) {
            return $empty;
        }

        $cutoff = now()->subSeconds(self::ONLINE_WINDOW_SECONDS);
        $base = ParticipantLiveAccess::query()
            ->where('course_id', $course->id)
            ->whereNotNull('embed_last_seen_at')
            ->where('embed_last_seen_at', '>=', $cutoff);

        $count = (int) (clone $base)->count();
        $rows = (clone $base)
            ->with(['participant' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'email');
            }])
            ->orderByDesc('embed_last_seen_at')
            ->limit(self::ONLINE_LIST_LIMIT)
            ->get();

        $viewers = [];
        foreach ($rows as $access) {
            $participant = $access->participant;
            if (! $participant instanceof Participant) {
                continue;
            }
            $name = trim((string) $participant->first_name.' '.(string) $participant->last_name);
            $viewers[] = [
                'id' => (int) $participant->id,
                'name' => $name !== '' ? $name : 'Uczestnik #'.$participant->id,
                'email' => (string) ($participant->email ?? ''),
            ];
        }

        return [
            'count' => $count,
            'viewers' => $viewers,
            'truncated' => $count > count($viewers),
            'window_seconds' => self::ONLINE_WINDOW_SECONDS,
        ];
    }
}
