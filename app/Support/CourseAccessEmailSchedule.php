<?php

namespace App\Support;

use App\Models\Course;
use Carbon\CarbonInterface;

/**
 * Jednolity format terminu szkolenia w mailach z dostępem:
 * data i godzina startu + czas trwania w nawiasie (bez daty/godziny zakończenia).
 */
class CourseAccessEmailSchedule
{
    /**
     * Etykieta „Data rozpoczęcia: …” (provision, link do live).
     */
    public static function prefixedStartLine(Course $course, bool $hideWhenPast = true): ?string
    {
        if (! self::shouldShowSchedule($course, $hideWhenPast)) {
            return null;
        }

        $formatted = self::formatStartDateTime($course);
        $duration = self::formatDurationInParentheses(self::durationMinutes($course));

        return 'Data rozpoczęcia: '.$formatted.$duration;
    }

    /**
     * Fragment do zdań typu „które odbyło się …” (nagrania, przypomnienia o wygaśnięciu).
     */
    public static function sentenceFragment(Course $course): ?string
    {
        if (! $course->start_date instanceof CarbonInterface) {
            return null;
        }

        $start = self::startInAppTimezone($course);
        $formatted = $start->locale('pl')->translatedFormat('j F Y \\r.').' o godz. '.$start->format('G:i');
        $duration = self::formatDurationInParentheses(self::durationMinutes($course));

        return $formatted.$duration;
    }

    public static function durationMinutes(Course $course): ?int
    {
        if (! $course->start_date instanceof CarbonInterface
            || ! $course->end_date instanceof CarbonInterface) {
            return null;
        }

        $start = self::startInAppTimezone($course);
        $end = $course->end_date->copy()->timezone(self::timezone());

        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        return (int) $start->diffInMinutes($end);
    }

    public static function formatDurationInParentheses(?int $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return '';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return ' ('.$hours.' godz. '.$remainingMinutes.' min.)';
        }

        if ($hours > 0) {
            return ' ('.$hours.' godz.)';
        }

        return ' ('.$minutes.' min.)';
    }

    private static function shouldShowSchedule(Course $course, bool $hideWhenPast): bool
    {
        if (! $course->start_date instanceof CarbonInterface) {
            return false;
        }

        if (! $hideWhenPast) {
            return true;
        }

        if ($course->end_date instanceof CarbonInterface && $course->end_date->isPast()) {
            return false;
        }

        if (! $course->end_date && $course->start_date->isPast()) {
            return false;
        }

        return true;
    }

    private static function formatStartDateTime(Course $course): string
    {
        return self::startInAppTimezone($course)->format('d.m.Y G:i');
    }

    private static function startInAppTimezone(Course $course): CarbonInterface
    {
        return $course->start_date->copy()->timezone(self::timezone());
    }

    private static function timezone(): string
    {
        return (string) config('app.timezone', 'Europe/Warsaw');
    }
}
