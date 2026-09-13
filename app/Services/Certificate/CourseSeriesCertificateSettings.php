<?php

namespace App\Services\Certificate;

use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\CourseSeries;
use Illuminate\Support\Facades\Log;

/**
 * Kopiuje domyślny format i szablon zaświadczeń z serii na nowo dodane szkolenia.
 *
 * Tylko w momencie dodania / usunięcia z serii. Kursy już przypisane nie są ruszane.
 */
class CourseSeriesCertificateSettings
{
    public const DEFAULT_PNE_FORMAT = '{nr}/{course_id}/{year}/PNE';

    /**
     * @param  array<int|string>  $previousCourseIds
     * @param  array<int|string>  $newCourseIds
     */
    public function applyMembershipChange(CourseSeries $series, array $previousCourseIds, array $newCourseIds): void
    {
        if (! $series->hasCertificateDefaults()) {
            return;
        }

        $previous = $this->normalizeIds($previousCourseIds);
        $next = $this->normalizeIds($newCourseIds);

        $attached = array_values(array_diff($next, $previous));
        $detached = array_values(array_diff($previous, $next));

        $this->applyOnAttach($series, $attached);
        $this->revertOnDetach($series, $detached);
    }

    /**
     * @param  array<int>  $courseIds
     */
    private function applyOnAttach(CourseSeries $series, array $courseIds): void
    {
        if ($courseIds === []) {
            return;
        }

        $seriesFormat = $this->normalizedSeriesFormat($series);
        $seriesTemplateId = $this->resolvedSeriesTemplateId($series);

        if ($seriesFormat === null && $seriesTemplateId === null) {
            return;
        }

        Course::query()
            ->whereIn('id', $courseIds)
            ->where('certificate_format', self::DEFAULT_PNE_FORMAT)
            ->get()
            ->each(function (Course $course) use ($seriesFormat, $seriesTemplateId): void {
                if (! $this->courseMayReceiveSeriesDefaults($course, $seriesTemplateId)) {
                    return;
                }

                if ($seriesFormat !== null) {
                    $course->certificate_format = $seriesFormat;
                }

                if ($seriesTemplateId !== null) {
                    $course->certificate_template_id = $seriesTemplateId;
                }

                $course->save();
            });
    }

    /**
     * Cofa tylko wartości nadal identyczne z ustawieniami serii.
     * Ręczna edycja na szkoleniu zostaje.
     *
     * @param  array<int>  $courseIds
     */
    private function revertOnDetach(CourseSeries $series, array $courseIds): void
    {
        if ($courseIds === []) {
            return;
        }

        $seriesFormat = $this->normalizedSeriesFormat($series);
        $seriesTemplateId = $this->resolvedSeriesTemplateId($series);

        Course::query()
            ->whereIn('id', $courseIds)
            ->get()
            ->each(function (Course $course) use ($seriesFormat, $seriesTemplateId): void {
                if (! $this->courseStillMatchesSeriesDefaults($course, $seriesFormat, $seriesTemplateId)) {
                    return;
                }

                $course->certificate_format = self::DEFAULT_PNE_FORMAT;
                $course->certificate_template_id = null;
                $course->save();
            });
    }

    private function courseMayReceiveSeriesDefaults(Course $course, ?int $seriesTemplateId): bool
    {
        if ($seriesTemplateId === null) {
            return true;
        }

        return $course->certificate_template_id === null
            || (int) $course->certificate_template_id === $seriesTemplateId;
    }

    private function courseStillMatchesSeriesDefaults(Course $course, ?string $seriesFormat, ?int $seriesTemplateId): bool
    {
        if ($seriesFormat !== null && $course->certificate_format !== $seriesFormat) {
            return false;
        }

        if ($seriesTemplateId !== null && (int) $course->certificate_template_id !== $seriesTemplateId) {
            return false;
        }

        return $seriesFormat !== null || $seriesTemplateId !== null;
    }

    private function normalizedSeriesFormat(CourseSeries $series): ?string
    {
        $format = trim((string) $series->certificate_format);

        return $format === '' ? null : $format;
    }

    private function resolvedSeriesTemplateId(CourseSeries $series): ?int
    {
        if ($series->certificate_template_id === null) {
            return null;
        }

        $templateId = (int) $series->certificate_template_id;

        if (! CertificateTemplate::query()->whereKey($templateId)->exists()) {
            Log::warning('Szablon zaświadczeń ustawiony w serii nie istnieje — pominięto szablon przy synchronizacji kursów.', [
                'course_series_id' => $series->id,
                'certificate_template_id' => $templateId,
            ]);

            return null;
        }

        return $templateId;
    }

    /**
     * @param  array<int|string>  $ids
     * @return array<int>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
