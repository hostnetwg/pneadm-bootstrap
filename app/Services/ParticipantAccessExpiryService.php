<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CoursePriceVariant;
use App\Models\PaymentDisplayOption;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Wylicza datę wygaśnięcia dostępu uczestnika wg wariantu cenowego lub (fallback) starej logiki z kursu.
 */
class ParticipantAccessExpiryService
{
    /**
     * Przy provisioning z zamówienia: jeśli jest wariant — reguły z wariantu; w przeciwnym razie logika „2 miesiące / access_duration_days”.
     */
    public function resolveAccessExpiresAtForFormOrderProvisioning(
        ?CoursePriceVariant $variant,
        Course $course,
        Carbon $now,
        ?Carbon $purchaseDate = null,
        bool $usePostEndRuleWhenCourseEnded = false
    ): ?Carbon
    {
        if ($usePostEndRuleWhenCourseEnded && $this->courseHasEnded($course, $now)) {
            return $this->expiresAtForPostEndPurchase($variant, $course, $purchaseDate ?? $now);
        }

        if ($variant !== null) {
            return $this->expiresAtFromPriceVariant($variant, $now);
        }

        return $this->legacyExpiresAtFromCourse($course, $now);
    }

    public function resolveAccessExpiresAtForExtension(
        Carbon $currentAccessExpiresAt,
        ?CoursePriceVariant $variant,
        Course $course,
        Carbon $now,
        ?Carbon $purchaseDate = null,
        bool $usePostEndRuleWhenCourseEnded = false
    ): ?Carbon
    {
        if ($usePostEndRuleWhenCourseEnded && $this->courseHasEnded($course, $now)) {
            return $this->expiresAtForPostEndAccessExtension(
                $currentAccessExpiresAt,
                $variant,
                $course,
                $purchaseDate ?? $now,
                $now
            );
        }

        if ($variant !== null) {
            return $this->expiresAtFromPriceVariantForExtension($currentAccessExpiresAt, $variant, $now);
        }

        return $this->legacyExpiresAtFromCourseForExtension($currentAccessExpiresAt, $course, $now);
    }

    public function defaultExpiresAtFromCourseEnd(Course $course): ?Carbon
    {
        $baseDate = $course->end_date ?: $course->start_date;
        if (! $baseDate) {
            return null;
        }

        return $this->expiresAtFromCourseOrGlobalPostEndRule($course, $baseDate->copy());
    }

    public function expiresAtForPostEndPurchase(?CoursePriceVariant $variant, Course $course, Carbon $baseDate): ?Carbon
    {
        if ($variant !== null) {
            $variantRule = (string) ($variant->post_end_access_rule ?? CoursePriceVariant::POST_END_RULE_INHERIT);
            if ($variantRule === CoursePriceVariant::POST_END_RULE_UNLIMITED) {
                return null;
            }

            if ($variantRule === CoursePriceVariant::POST_END_RULE_DURATION
                && $variant->post_end_access_duration_value
                && $variant->post_end_access_duration_unit
            ) {
                return $this->expiresAtFromDuration(
                    $baseDate,
                    (string) $variant->post_end_access_duration_unit,
                    (int) $variant->post_end_access_duration_value
                );
            }
        }

        return $this->expiresAtFromCourseOrGlobalPostEndRule($course, $baseDate);
    }

    public function expiresAtFromPriceVariant(CoursePriceVariant $variant, Carbon $now): ?Carbon
    {
        $accessType = (string) $variant->access_type;

        switch ($accessType) {
            case '1':
            case '2':
                return null;

            case '3':
                if ($variant->access_duration_value && $variant->access_duration_unit) {
                    $expiresAt = $now->copy();
                    $this->addDuration($expiresAt, (string) $variant->access_duration_unit, (int) $variant->access_duration_value);

                    return $expiresAt;
                }

                return null;

            case '4':
                if ($variant->access_end_datetime) {
                    return Carbon::parse($variant->access_end_datetime);
                }

                return null;

            case '5':
                $startDate = $variant->access_start_datetime
                    ? Carbon::parse($variant->access_start_datetime)
                    : $now;

                if ($variant->access_duration_value && $variant->access_duration_unit) {
                    $expiresAt = $startDate->copy();
                    $this->addDuration($expiresAt, (string) $variant->access_duration_unit, (int) $variant->access_duration_value);

                    return $expiresAt;
                }

                return null;

            default:
                Log::warning('ParticipantAccessExpiryService: nieznany typ dostępu w wariancie cenowym', [
                    'access_type' => $accessType,
                    'variant_id' => $variant->id,
                    'course_id' => $variant->course_id,
                ]);

                return null;
        }
    }

    public function expiresAtFromPriceVariantForExtension(
        Carbon $currentAccessExpiresAt,
        CoursePriceVariant $variant,
        Carbon $now
    ): ?Carbon
    {
        $accessType = (string) $variant->access_type;

        switch ($accessType) {
            case '1':
            case '2':
                return null;

            case '3':
                if ($variant->access_duration_value && $variant->access_duration_unit) {
                    return $this->expiresAtFromDuration(
                        $this->extensionBaseDate($currentAccessExpiresAt, $now, $now),
                        (string) $variant->access_duration_unit,
                        (int) $variant->access_duration_value
                    );
                }

                return null;

            case '4':
                if (! $variant->access_end_datetime) {
                    return null;
                }

                $variantEnd = Carbon::parse($variant->access_end_datetime);

                return $currentAccessExpiresAt->gt($variantEnd) ? $currentAccessExpiresAt->copy() : $variantEnd;

            case '5':
                if ($variant->access_duration_value && $variant->access_duration_unit) {
                    $fallbackBaseDate = $variant->access_start_datetime
                        ? Carbon::parse($variant->access_start_datetime)
                        : $now;

                    return $this->expiresAtFromDuration(
                        $this->extensionBaseDate($currentAccessExpiresAt, $fallbackBaseDate, $now),
                        (string) $variant->access_duration_unit,
                        (int) $variant->access_duration_value
                    );
                }

                return null;

            default:
                Log::warning('ParticipantAccessExpiryService: nieznany typ dostępu przy przedłużaniu', [
                    'access_type' => $accessType,
                    'variant_id' => $variant->id,
                    'course_id' => $variant->course_id,
                ]);

                return null;
        }
    }

    /**
     * Poprzednia logika FormOrderPneduProvisionService / Publigo (bez wariantu na zamówieniu).
     */
    public function legacyExpiresAtFromCourse(Course $course, Carbon $now): ?Carbon
    {
        if ($course->start_date) {
            $startDate = $course->start_date;
            if ($now->lt($startDate)) {
                return $startDate->copy()->addMonths(2);
            }

            return $now->copy()->addMonths(2);
        }

        if ($course->access_duration_days) {
            return $now->copy()->addDays((int) $course->access_duration_days);
        }

        return null;
    }

    public function legacyExpiresAtFromCourseForExtension(Carbon $currentAccessExpiresAt, Course $course, Carbon $now): ?Carbon
    {
        if ($course->start_date || $course->access_duration_days) {
            $baseDate = $this->extensionBaseDate($currentAccessExpiresAt, $now, $now);

            if ($course->access_duration_days) {
                return $baseDate->copy()->addDays((int) $course->access_duration_days);
            }

            return $baseDate->copy()->addMonths(2);
        }

        return null;
    }

    private function addDuration(Carbon $target, string $unit, int $value): void
    {
        match ($unit) {
            'hours' => $target->addHours($value),
            'days' => $target->addDays($value),
            'weeks' => $target->addWeeks($value),
            'months' => $target->addMonths($value),
            'years' => $target->addYears($value),
            default => null,
        };
    }

    private function courseHasEnded(Course $course, Carbon $now): bool
    {
        return $course->end_date !== null && $now->gt($course->end_date);
    }

    private function expiresAtFromCourseOrGlobalPostEndRule(Course $course, Carbon $baseDate): ?Carbon
    {
        $value = $course->post_end_access_duration_value;
        $unit = $course->post_end_access_duration_unit;

        if (! $value || ! $unit) {
            $settings = PaymentDisplayOption::getSettings();
            $value = $settings->default_post_end_access_duration_value ?: 2;
            $unit = $settings->default_post_end_access_duration_unit ?: 'months';
        }

        return $this->expiresAtFromDuration($baseDate, (string) $unit, (int) $value);
    }

    private function expiresAtForPostEndAccessExtension(
        Carbon $currentAccessExpiresAt,
        ?CoursePriceVariant $variant,
        Course $course,
        Carbon $purchaseDate,
        Carbon $now
    ): ?Carbon
    {
        if ($variant !== null) {
            $variantRule = (string) ($variant->post_end_access_rule ?? CoursePriceVariant::POST_END_RULE_INHERIT);
            if ($variantRule === CoursePriceVariant::POST_END_RULE_UNLIMITED) {
                return null;
            }

            if ($variantRule === CoursePriceVariant::POST_END_RULE_DURATION
                && $variant->post_end_access_duration_value
                && $variant->post_end_access_duration_unit
            ) {
                return $this->expiresAtFromDuration(
                    $this->extensionBaseDate($currentAccessExpiresAt, $purchaseDate, $now),
                    (string) $variant->post_end_access_duration_unit,
                    (int) $variant->post_end_access_duration_value
                );
            }
        }

        return $this->expiresAtFromCourseOrGlobalPostEndRuleForExtension($currentAccessExpiresAt, $course, $purchaseDate, $now);
    }

    private function expiresAtFromCourseOrGlobalPostEndRuleForExtension(
        Carbon $currentAccessExpiresAt,
        Course $course,
        Carbon $purchaseDate,
        Carbon $now
    ): ?Carbon
    {
        $value = $course->post_end_access_duration_value;
        $unit = $course->post_end_access_duration_unit;

        if (! $value || ! $unit) {
            $settings = PaymentDisplayOption::getSettings();
            $value = $settings->default_post_end_access_duration_value ?: 2;
            $unit = $settings->default_post_end_access_duration_unit ?: 'months';
        }

        return $this->expiresAtFromDuration(
            $this->extensionBaseDate($currentAccessExpiresAt, $purchaseDate, $now),
            (string) $unit,
            (int) $value
        );
    }

    private function extensionBaseDate(Carbon $currentAccessExpiresAt, Carbon $fallbackBaseDate, Carbon $now): Carbon
    {
        return $currentAccessExpiresAt->gt($now)
            ? $currentAccessExpiresAt->copy()
            : $fallbackBaseDate->copy();
    }

    private function expiresAtFromDuration(Carbon $baseDate, string $unit, int $value): Carbon
    {
        $expiresAt = $baseDate->copy();
        $this->addDuration($expiresAt, $unit, $value);

        return $expiresAt;
    }
}
