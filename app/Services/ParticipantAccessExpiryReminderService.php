<?php

namespace App\Services;

use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\Participant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ParticipantAccessExpiryReminderService
{
    /**
     * @return list<int>
     */
    public function configuredDaysBefore(): array
    {
        $days = config('participant_access.expiry_reminder.days_before', [7, 1]);
        $days = array_values(array_unique(array_filter(array_map('intval', $days), fn (int $d) => $d > 0)));
        sort($days);

        return $days;
    }

    public function timezone(): string
    {
        return (string) config('participant_access.expiry_reminder.timezone', 'Europe/Warsaw');
    }

    /**
     * Uczestnicy kwalifikujący się do przypomnienia w danym dniu (offset od dziś).
     *
     * @return Collection<int, Participant>
     */
    public function participantsDueForReminder(int $daysBefore, ?Carbon $referenceDay = null): Collection
    {
        if ($daysBefore < 1) {
            return collect();
        }

        $tz = $this->timezone();
        $referenceDay ??= Carbon::now($tz)->startOfDay();
        $targetDay = $referenceDay->copy()->addDays($daysBefore);

        $rangeStartUtc = $targetDay->copy()->startOfDay()->utc();
        $rangeEndUtc = $targetDay->copy()->endOfDay()->utc();

        return Participant::query()
            ->with(['course'])
            ->whereNotNull('participants.access_expires_at')
            ->where('participants.access_expires_at', '>', now('UTC'))
            ->whereNotNull('participants.email')
            ->where('participants.email', '!=', '')
            ->whereBetween('participants.access_expires_at', [$rangeStartUtc, $rangeEndUtc])
            ->whereHas('course', function (Builder $q) {
                $q->where('is_paid', true)->whereNull('deleted_at');
            })
            ->where(function (Builder $q) {
                $q->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('course_videos')
                        ->whereColumn('course_videos.course_id', 'participants.course_id');
                })->orWhereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('course_file_links')
                        ->whereColumn('course_file_links.course_id', 'participants.course_id');
                });
            })
            ->whereNotExists(function ($q) use ($daysBefore) {
                $this->applyAlreadySentReminderConstraint($q, $daysBefore);
            })
            ->orderBy('participants.id')
            ->get();
    }

    public function reminderWasSent(int $participantId, int $courseId, int $daysBefore): bool
    {
        return CertificateEmailLog::query()
            ->where('participant_id', $participantId)
            ->where('course_id', $courseId)
            ->where('type', CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER)
            ->where('status', CertificateEmailLog::STATUS_SENT)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.days_before')) = ?", [(string) $daysBefore])
            ->exists();
    }

    /**
     * Liczba pełnych dni kalendarzowych do wygaśnięcia (0 = dziś, null = brak / już wygasło).
     */
    public function daysUntilExpiry(Participant $participant, ?Carbon $referenceDay = null): ?int
    {
        if ($participant->access_expires_at === null) {
            return null;
        }

        $tz = $this->timezone();
        $referenceDay ??= Carbon::now($tz)->startOfDay();
        $expiresDay = $participant->access_expires_at->copy()->timezone($tz)->startOfDay();

        if ($expiresDay->lt($referenceDay)) {
            return null;
        }

        return (int) $referenceDay->diffInDays($expiresDay, false);
    }

    /**
     * Czy można wysłać przypomnienie (automat lub ręcznie) dla uczestnika.
     *
     * @return array{eligible: bool, reason: string|null}
     */
    public function eligibilityForParticipant(Participant $participant, Course $course, bool $hasVideos, bool $hasMaterials): array
    {
        if (! $course->is_paid) {
            return ['eligible' => false, 'reason' => 'Szkolenie nie jest płatne — automatyczne przypomnienia dotyczą tylko płatnych.'];
        }

        $email = trim((string) ($participant->email ?? ''));
        if ($email === '') {
            return ['eligible' => false, 'reason' => 'Brak adresu e-mail uczestnika.'];
        }

        if ($participant->access_expires_at === null) {
            return ['eligible' => false, 'reason' => 'Dostęp bezterminowy — brak daty wygaśnięcia.'];
        }

        if ($participant->hasExpiredAccess()) {
            return ['eligible' => false, 'reason' => 'Dostęp już wygasł.'];
        }

        if (! $hasVideos && ! $hasMaterials) {
            return ['eligible' => false, 'reason' => 'Brak nagrań i materiałów do przypomnienia.'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Uczestnicy kursu kwalifikujący się do ręcznego przypomnienia (cały kurs, nie tylko bieżąca strona).
     *
     * @return Collection<int, Participant>
     */
    public function eligibleParticipantsForCourse(Course $course, bool $hasVideos, bool $hasMaterials): Collection
    {
        if (! $course->is_paid || (! $hasVideos && ! $hasMaterials)) {
            return collect();
        }

        return Participant::query()
            ->where('course_id', $course->id)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '>', now('UTC'))
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->filter(function (Participant $participant) use ($course, $hasVideos, $hasMaterials) {
                return $this->eligibilityForParticipant($participant, $course, $hasVideos, $hasMaterials)['eligible'];
            })
            ->values();
    }

    /**
     * @return array{enabled: bool, days_before: list<int>, timezone: string, schedule_time: string, days_label: string}
     */
    public function scheduleSummary(): array
    {
        $days = $this->configuredDaysBefore();
        $labels = array_map(fn (int $d) => $d === 1 ? '1 dzień' : $d.' dni', $days);

        return [
            'enabled' => (bool) config('participant_access.expiry_reminder.enabled', true),
            'days_before' => $days,
            'timezone' => $this->timezone(),
            'schedule_time' => (string) config('participant_access.expiry_reminder.schedule_time', '08:00'),
            'days_label' => implode(' oraz ', $labels),
        ];
    }

    private function applyAlreadySentReminderConstraint($q, int $daysBefore): void
    {
        $q->selectRaw('1')
            ->from('certificate_email_logs')
            ->whereColumn('certificate_email_logs.participant_id', 'participants.id')
            ->whereColumn('certificate_email_logs.course_id', 'participants.course_id')
            ->where('certificate_email_logs.type', CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER)
            ->where('certificate_email_logs.status', CertificateEmailLog::STATUS_SENT)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(certificate_email_logs.meta, '$.days_before')) = ?", [(string) $daysBefore]);
    }
}
