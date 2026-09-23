<?php

namespace App\Services;

use App\Jobs\SendCourseAccessEmailJob;
use App\Models\CertificateEmailLog;
use App\Support\PneduRegistrationLink;
use App\Models\Course;
use App\Models\CourseFileLink;
use App\Models\CourseVideo;
use App\Models\Participant;
use App\Models\PneduUser;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecordingEnrollmentService
{
    public function __construct(
        private readonly ParticipantAccessExpiryService $accessExpiry,
    ) {}

    /**
     * @param  array{first_name: string, last_name: string, email: string}  $data
     * @return array{
     *     success: bool,
     *     updated?: bool,
     *     has_pnedu_account?: bool,
     *     next_url?: string|null,
     *     email_sent?: bool,
     *     email_failed?: bool,
     *     message: string,
     *     http_code: int
     * }
     */
    public function register(Course $course, array $data): array
    {
        if (! $course->isRecordingEnrollmentActiveNow()) {
            return [
                'success' => false,
                'message' => $course->recordingEnrollmentInactiveMessage(),
                'http_code' => 403,
            ];
        }

        $emailNormalized = Participant::normalizeEmail($data['email']);
        if ($emailNormalized === null) {
            return [
                'success' => false,
                'message' => 'Podaj prawidłowy adres e-mail.',
                'http_code' => 422,
            ];
        }

        $maxAttempts = 3;
        $saved = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $saved = DB::transaction(function () use ($course, $data, $emailNormalized) {
                    return $this->saveParticipant($course, $data, $emailNormalized);
                });
                break;
            } catch (UniqueConstraintViolationException|QueryException $e) {
                if ($attempt >= $maxAttempts - 1 || ! $this->isDuplicateKeyException($e)) {
                    throw $e;
                }
            }
        }

        if (! is_array($saved) || ! ($saved['ok'] ?? false)) {
            return [
                'success' => false,
                'message' => $saved['message'] ?? 'Nie udało się zapisać uczestnika.',
                'http_code' => (int) ($saved['http_code'] ?? 422),
            ];
        }

        $participant = $saved['participant'] ?? null;
        if (! $participant instanceof Participant) {
            return [
                'success' => false,
                'message' => 'Nie udało się zapisać uczestnika.',
                'http_code' => 500,
            ];
        }

        $hasAccount = $this->hasPneduAccount($emailNormalized);
        $email = trim((string) $participant->email);
        $nextUrl = $hasAccount
            ? $this->loginUrl($email)
            : PneduRegistrationLink::url($email, $participant->first_name, $participant->last_name);

        $emailSent = false;
        $emailFailed = false;

        try {
            $emailSent = $this->sendCourseAccessEmail($course, $participant);
        } catch (\Throwable $e) {
            $emailFailed = true;
            Log::error('RecordingEnrollmentService: nie udało się wysłać e-maila nagrania', [
                'course_id' => $course->id,
                'participant_id' => $participant->id,
                'exception' => $e->getMessage(),
            ]);
        }

        $message = $hasAccount
            ? 'Jesteś na liście szkolenia. Zaloguj się, żeby obejrzeć nagranie.'
            : 'Jesteś na liście szkolenia. Nagranie obejrzysz po założeniu konta na pnedu.pl.';

        if ($emailFailed) {
            $message .= ' Wiadomość e-mail nie wyszła — użyj przycisku poniżej albo napisz na kontakt@pnedu.pl.';
        }

        return [
            'success' => true,
            'updated' => $saved['updated'],
            'has_pnedu_account' => $hasAccount,
            'next_url' => $nextUrl,
            'email_sent' => $emailSent,
            'email_failed' => $emailFailed,
            'message' => $message,
            'http_code' => 200,
        ];
    }

    /**
     * @param  array{first_name: string, last_name: string, email: string}  $data
     * @return array{ok: bool, updated?: bool, participant?: Participant, message?: string, http_code?: int}
     */
    private function saveParticipant(Course $course, array $data, string $emailNormalized): array
    {
        $lockedCourse = Course::query()->lockForUpdate()->find($course->id);
        if ($lockedCourse === null || ! $lockedCourse->isRecordingEnrollmentActiveNow()) {
            return [
                'ok' => false,
                'message' => $lockedCourse?->recordingEnrollmentInactiveMessage()
                    ?? 'Dopisywanie do nagrania jest wyłączone.',
                'http_code' => 403,
            ];
        }

        $existing = $this->findExistingParticipant($lockedCourse->id, $emailNormalized);

        if ($existing && $existing->hasExpiredAccess()) {
            return [
                'ok' => false,
                'message' => 'Jesteś już na liście tego szkolenia, ale dostęp do nagrania wygasł. Napisz na kontakt@pnedu.pl.',
                'http_code' => 403,
            ];
        }

        $attributes = [
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => trim($data['email']),
            'email_normalized' => $emailNormalized,
        ];

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->update($attributes);

            return ['ok' => true, 'updated' => true, 'participant' => $existing->fresh() ?? $existing];
        }

        $order = (int) $lockedCourse->next_participant_order;
        $lockedCourse->increment('next_participant_order');

        $participant = Participant::create(array_merge($attributes, [
            'course_id' => $lockedCourse->id,
            'order' => $order,
            'access_expires_at' => $this->accessExpiry->defaultExpiresAtFromCourseEnd($lockedCourse),
        ]));

        return ['ok' => true, 'updated' => false, 'participant' => $participant];
    }

    private function hasPneduAccount(string $emailNormalized): bool
    {
        try {
            return PneduUser::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNormalized])
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('RecordingEnrollmentService: nie udało się sprawdzić konta pnedu', [
                'email' => $emailNormalized,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Ten sam e-mail co przycisk „Wyślij e-mail nagranie”. Bez nagrania, materiałów i zaświadczenia nic nie wychodzi.
     */
    private function sendCourseAccessEmail(Course $course, Participant $participant): bool
    {
        $hasVideos = CourseVideo::query()->where('course_id', $course->id)->exists();
        $hasMaterials = CourseFileLink::query()->where('course_id', $course->id)->exists();
        $hasCertificate = $course->certificate_download_status === 'download_enabled';

        if (! $hasVideos && ! $hasMaterials && ! $hasCertificate) {
            return false;
        }

        $log = CertificateEmailLog::create([
            'course_id' => $course->id,
            'participant_id' => $participant->id,
            'type' => CertificateEmailLog::TYPE_COURSE_ACCESS,
            'status' => CertificateEmailLog::STATUS_QUEUED,
            'created_by' => null,
            'queued_at' => now(),
            'meta' => [
                'has_videos' => $hasVideos,
                'has_materials' => $hasMaterials,
                'has_certificate' => $hasCertificate,
                'source' => 'recording_enrollment',
            ],
        ]);

        SendCourseAccessEmailJob::dispatchSync($course->id, $participant->id, $log->id);

        return true;
    }

    private function loginUrl(string $email): string
    {
        $base = rtrim((string) config('services.pnedu_frontend_url'), '/');

        return $base.'/login?'.http_build_query(['email' => $email]);
    }

    private function findExistingParticipant(int $courseId, string $emailNormalized): ?Participant
    {
        $existing = Participant::withTrashed()
            ->where('course_id', $courseId)
            ->where('email_normalized', $emailNormalized)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Participant::withTrashed()
            ->where('course_id', $courseId)
            ->whereNull('email_normalized')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNormalized])
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $sqlState === '23505' || $driverCode === 1062;
    }
}
