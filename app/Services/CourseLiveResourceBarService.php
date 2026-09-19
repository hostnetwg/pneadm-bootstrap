<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Models\CourseSurveyLink;
use App\Models\ParticipantLiveAccess;

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

    /**
     * Osadzony /transmisja jest dziś tylko dla zalogowanego uczestnika — rejestracja na belce
     * wróci, gdy live będzie dostępny bez konta pnedu (np. zamknięty link od dyrektora).
     */
    public const ATTENDANCE_VISIBLE_ON_AUTHENTICATED_EMBED = false;

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
     *     embed_entries: array{ever: int, recent_15min: int}
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

        return [
            'flags' => [
                'attendance' => (bool) ($details?->live_bar_attendance_enabled),
                'certificate' => (bool) ($details?->live_bar_certificate_enabled),
                'materials' => (bool) ($details?->live_bar_materials_enabled),
                'survey' => (bool) ($details?->live_bar_survey_enabled),
            ],
            'offer' => $this->offerState($course, $details),
            'resources' => [
                'attendance' => [
                    'ready' => self::ATTENDANCE_VISIBLE_ON_AUTHENTICATED_EMBED && $attendanceUrl !== null,
                    'parked' => ! self::ATTENDANCE_VISIBLE_ON_AUTHENTICATED_EMBED,
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
            'links' => $this->visibleLinks($course),
            'embed_on_pnedu' => (bool) ($details?->embed_on_pnedu),
            'has_online_details' => $details !== null,
            'embed_entries' => $this->embedEntryCounts($course),
        ];
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
     * @return array{enabled: bool, course_id: ?int, course: ?array<string, mixed>}
     */
    public function offerState(Course $liveCourse, ?CourseOnlineDetails $details): array
    {
        $empty = [
            'enabled' => false,
            'course_id' => null,
            'course' => null,
        ];

        $offerId = (int) ($details?->live_offer_course_id ?? 0);
        if ($offerId <= 0 || $offerId === (int) $liveCourse->id) {
            return $empty;
        }

        $offerCourse = Course::query()
            ->with('instructor:id,title,first_name,last_name')
            ->find($offerId);

        if (! $offerCourse instanceof Course) {
            return $empty;
        }

        return [
            'enabled' => (bool) ($details?->live_offer_enabled),
            'course_id' => (int) $offerCourse->id,
            'course' => $this->adminSelectItem($offerCourse),
        ];
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
}
