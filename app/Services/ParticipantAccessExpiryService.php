<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CoursePriceVariant;
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
    public function resolveAccessExpiresAtForFormOrderProvisioning(?CoursePriceVariant $variant, Course $course, Carbon $now): ?Carbon
    {
        if ($variant !== null) {
            return $this->expiresAtFromPriceVariant($variant, $now);
        }

        return $this->legacyExpiresAtFromCourse($course, $now);
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

    private function addDuration(Carbon $target, string $unit, int $value): void
    {
        match ($unit) {
            'hours' => $target->addHours($value),
            'days' => $target->addDays($value),
            'months' => $target->addMonths($value),
            'years' => $target->addYears($value),
            default => null,
        };
    }
}
