<?php

namespace App\Services;

use App\Jobs\SendOnlineCoursePlatformMigrationEmailJob;
use App\Mail\OnlineCoursePlatformMigrationMail;
use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use App\Models\OnlineCourseEnrollmentEmailLog;
use App\Models\PneduUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class OnlineCourseEnrollmentPlatformMigrationMailService
{
    /**
     * @return array{success: bool, error: ?string, email: ?string}
     */
    public function sendToEnrollment(
        OnlineCourse $course,
        OnlineCourseEnrollment $enrollment,
        ?int $createdBy = null,
        bool $sync = true
    ): array {
        if ((int) $enrollment->online_course_id !== (int) $course->id) {
            return ['success' => false, 'error' => 'Zapis nie należy do tego kursu.', 'email' => null];
        }

        $email = trim((string) ($enrollment->email ?? ''));
        if ($email === '' || ! str_contains($email, '@')) {
            return ['success' => false, 'error' => 'Brak prawidłowego adresu e-mail.', 'email' => null];
        }

        $log = OnlineCourseEnrollmentEmailLog::query()->create([
            'online_course_id' => $course->id,
            'online_course_enrollment_id' => $enrollment->id,
            'type' => OnlineCourseEnrollmentEmailLog::TYPE_PLATFORM_MIGRATION,
            'status' => OnlineCourseEnrollmentEmailLog::STATUS_QUEUED,
            'created_by' => $createdBy,
            'queued_at' => now(),
        ]);

        $job = new SendOnlineCoursePlatformMigrationEmailJob($course->id, $enrollment->id, $log->id);
        if ($sync) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }

        return ['success' => true, 'error' => null, 'email' => $email];
    }

    /**
     * @return array{success: bool, error: ?string, queued: int}
     */
    public function sendBulk(OnlineCourse $course, string $mode, ?int $createdBy = null): array
    {
        if (! in_array($mode, ['unsent', 'resend_all'], true)) {
            return ['success' => false, 'error' => 'Nieprawidłowy tryb wysyłki.', 'queued' => 0];
        }

        $enrollments = $this->eligibleForBulkQuery($course, $mode)->get();
        if ($enrollments->isEmpty()) {
            return ['success' => false, 'error' => 'Brak osób spełniających warunki wysyłki (ważny dostęp).', 'queued' => 0];
        }

        $jobs = [];
        $logIds = [];
        foreach ($enrollments as $enrollment) {
            $log = OnlineCourseEnrollmentEmailLog::query()->create([
                'online_course_id' => $course->id,
                'online_course_enrollment_id' => $enrollment->id,
                'type' => OnlineCourseEnrollmentEmailLog::TYPE_PLATFORM_MIGRATION,
                'status' => OnlineCourseEnrollmentEmailLog::STATUS_QUEUED,
                'created_by' => $createdBy,
                'queued_at' => now(),
            ]);
            $logIds[] = $log->id;
            $jobs[] = new SendOnlineCoursePlatformMigrationEmailJob($course->id, $enrollment->id, $log->id);
        }

        $batch = Bus::batch($jobs)
            ->name($this->batchName($course))
            ->dispatch();

        OnlineCourseEnrollmentEmailLog::query()
            ->whereIn('id', $logIds)
            ->update(['batch_id' => $batch->id]);

        return ['success' => true, 'error' => null, 'queued' => count($jobs)];
    }

    /**
     * @return array{
     *     success: bool,
     *     error: ?string,
     *     http_code: int,
     *     to?: string,
     *     subject?: string,
     *     body_html?: string,
     *     has_pnedu_account?: bool,
     *     access_expired?: bool,
     *     variant_label?: string,
     *     recipient_count?: int,
     *     sample_notice?: string
     * }
     */
    public function previewForEnrollment(OnlineCourse $course, OnlineCourseEnrollment $enrollment): array
    {
        $built = $this->makeMailable($course, $enrollment);
        if (! ($built['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $built['error'] ?? 'Nie udało się przygotować podglądu.',
                'http_code' => 422,
            ];
        }

        try {
            $mail = $built['mailable']->build();

            return [
                'success' => true,
                'http_code' => 200,
                'to' => $built['email'],
                'subject' => (string) ($mail->subject ?? ''),
                'body_html' => $mail->render(),
                'has_pnedu_account' => $built['has_pnedu_account'],
                'access_expired' => $built['access_expired'],
                'variant_label' => $built['has_pnedu_account']
                    ? 'Wariant: osoba ma konto na pnedu.pl (logowanie + link do kursu)'
                    : 'Wariant: brak konta na pnedu.pl (rejestracja na ten sam e-mail)',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Nie udało się przygotować podglądu: '.$e->getMessage(),
                'http_code' => 500,
            ];
        }
    }

    /**
     * @return array{
     *     success: bool,
     *     error: ?string,
     *     http_code: int,
     *     to?: string,
     *     subject?: string,
     *     body_html?: string,
     *     has_pnedu_account?: bool,
     *     access_expired?: bool,
     *     variant_label?: string,
     *     recipient_count?: int,
     *     sample_notice?: string
     * }
     */
    public function previewBulk(OnlineCourse $course, string $mode): array
    {
        if (! in_array($mode, ['unsent', 'resend_all'], true)) {
            return ['success' => false, 'error' => 'Nieprawidłowy tryb wysyłki.', 'http_code' => 422];
        }

        $query = $this->eligibleForBulkQuery($course, $mode);
        $count = (clone $query)->count();
        $sample = (clone $query)->orderBy('email')->first();
        if (! $sample) {
            return [
                'success' => false,
                'error' => 'Brak osób spełniających warunki wysyłki (ważny dostęp).',
                'http_code' => 422,
                'recipient_count' => 0,
            ];
        }

        $preview = $this->previewForEnrollment($course, $sample);
        $preview['recipient_count'] = $count;
        $preview['sample_notice'] = 'Podgląd przykładowy dla '.$sample->email
            .' (pierwsza osoba z listy). Każdy odbiorca dostaje swoją wersję: imię, e-mail i wariant z kontem albo bez.';

        return $preview;
    }

    /**
     * @return array{
     *     success: bool,
     *     error: ?string,
     *     email: ?string,
     *     mailable: ?OnlineCoursePlatformMigrationMail,
     *     has_pnedu_account: bool,
     *     access_expired: bool,
     *     course_url: ?string
     * }
     */
    public function makeMailable(OnlineCourse $course, OnlineCourseEnrollment $enrollment): array
    {
        if ((int) $enrollment->online_course_id !== (int) $course->id) {
            return [
                'success' => false,
                'error' => 'Zapis nie należy do tego kursu.',
                'email' => null,
                'mailable' => null,
                'has_pnedu_account' => false,
                'access_expired' => false,
                'course_url' => null,
            ];
        }

        $email = trim((string) ($enrollment->email ?? ''));
        if ($email === '' || ! str_contains($email, '@')) {
            return [
                'success' => false,
                'error' => 'Brak prawidłowego adresu e-mail.',
                'email' => null,
                'mailable' => null,
                'has_pnedu_account' => false,
                'access_expired' => false,
                'course_url' => null,
            ];
        }

        $pneduFrontendUrl = rtrim((string) config('services.pnedu_frontend_url', 'http://localhost:8081'), '/');
        $normalizedEmail = OnlineCourseEnrollment::normalizeEmail($email) ?? strtolower($email);
        $hasPneduAccount = false;
        try {
            $hasPneduAccount = PneduUser::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                ->exists();
        } catch (\Throwable) {
            $hasPneduAccount = false;
        }

        $loginUrl = $pneduFrontendUrl.'/login';
        $registerUrl = $pneduFrontendUrl.'/register?email='.urlencode($email);
        $forgotPasswordUrl = $pneduFrontendUrl.'/forgot-password';
        $courseUrl = $pneduFrontendUrl.'/dashboard/kursy-online/'.$enrollment->id;
        $accessExpired = $enrollment->hasExpiredAccess();
        $accessExpiresAtFormatted = null;
        if ($enrollment->access_expires_at) {
            $accessExpiresAtFormatted = $enrollment->access_expires_at
                ->copy()
                ->timezone('Europe/Warsaw')
                ->format('d.m.Y H:i');
        }

        return [
            'success' => true,
            'error' => null,
            'email' => $email,
            'mailable' => new OnlineCoursePlatformMigrationMail(
                enrollment: $enrollment,
                course: $course,
                hasPneduAccount: $hasPneduAccount,
                participantEmail: $email,
                loginUrl: $loginUrl,
                registerUrl: $registerUrl,
                forgotPasswordUrl: $forgotPasswordUrl,
                courseUrl: $hasPneduAccount ? $courseUrl : null,
                accessExpiresAtFormatted: $accessExpiresAtFormatted,
                accessExpired: $accessExpired,
            ),
            'has_pnedu_account' => $hasPneduAccount,
            'access_expired' => $accessExpired,
            'course_url' => $hasPneduAccount ? $courseUrl : null,
        ];
    }

    public function eligibleCount(OnlineCourse $course): int
    {
        return $this->activeAccessQuery($course)->count();
    }

    /**
     * @return array{sent: int, queued: int, failed_without_sent: int, eligible: int}
     */
    public function courseStats(OnlineCourse $course): array
    {
        $type = OnlineCourseEnrollmentEmailLog::TYPE_PLATFORM_MIGRATION;
        $eligible = $this->eligibleCount($course);

        $sent = (int) $this->activeAccessQuery($course)
            ->whereExists(function ($q) use ($course, $type) {
                $q->selectRaw('1')
                    ->from('online_course_enrollment_email_logs')
                    ->whereColumn('online_course_enrollment_email_logs.online_course_enrollment_id', 'online_course_enrollments.id')
                    ->where('online_course_enrollment_email_logs.online_course_id', $course->id)
                    ->where('online_course_enrollment_email_logs.type', $type)
                    ->where('online_course_enrollment_email_logs.status', OnlineCourseEnrollmentEmailLog::STATUS_SENT);
            })
            ->count();

        $queued = (int) OnlineCourseEnrollmentEmailLog::query()
            ->where('online_course_id', $course->id)
            ->where('type', $type)
            ->where('status', OnlineCourseEnrollmentEmailLog::STATUS_QUEUED)
            ->distinct()
            ->count('online_course_enrollment_id');

        $failedWithoutSent = (int) $this->activeAccessQuery($course)
            ->whereExists(function ($q) use ($course, $type) {
                $q->selectRaw('1')
                    ->from('online_course_enrollment_email_logs')
                    ->whereColumn('online_course_enrollment_email_logs.online_course_enrollment_id', 'online_course_enrollments.id')
                    ->where('online_course_enrollment_email_logs.online_course_id', $course->id)
                    ->where('online_course_enrollment_email_logs.type', $type)
                    ->where('online_course_enrollment_email_logs.status', OnlineCourseEnrollmentEmailLog::STATUS_FAILED);
            })
            ->whereNotExists(function ($q) use ($course, $type) {
                $q->selectRaw('1')
                    ->from('online_course_enrollment_email_logs')
                    ->whereColumn('online_course_enrollment_email_logs.online_course_enrollment_id', 'online_course_enrollments.id')
                    ->where('online_course_enrollment_email_logs.online_course_id', $course->id)
                    ->where('online_course_enrollment_email_logs.type', $type)
                    ->where('online_course_enrollment_email_logs.status', OnlineCourseEnrollmentEmailLog::STATUS_SENT);
            })
            ->count();

        return [
            'sent' => $sent,
            'queued' => $queued,
            'failed_without_sent' => $failedWithoutSent,
            'eligible' => $eligible,
        ];
    }

    /**
     * @param  iterable<mixed>  $enrollmentIds
     * @return array<int, array{sent_count: int, last_sent_at: ?Carbon, has_queued: bool, has_failed: bool, last_error: ?string, last_delivery: ?array}>
     */
    public function statusByEnrollmentIds(OnlineCourse $course, iterable $enrollmentIds): array
    {
        $ids = collect($enrollmentIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $empty = [
            'sent_count' => 0,
            'last_sent_at' => null,
            'has_queued' => false,
            'has_failed' => false,
            'last_error' => null,
            'last_delivery' => null,
            'sent_without_real_delivery' => false,
        ];

        $byId = [];
        foreach ($ids as $id) {
            $byId[$id] = $empty;
        }

        if ($ids->isEmpty()) {
            return $byId;
        }

        $logs = OnlineCourseEnrollmentEmailLog::query()
            ->where('online_course_id', $course->id)
            ->where('type', OnlineCourseEnrollmentEmailLog::TYPE_PLATFORM_MIGRATION)
            ->whereIn('online_course_enrollment_id', $ids)
            ->orderByDesc('id')
            ->get();

        foreach ($logs as $log) {
            $eid = (int) $log->online_course_enrollment_id;
            if (! isset($byId[$eid])) {
                continue;
            }

            if ($log->status === OnlineCourseEnrollmentEmailLog::STATUS_SENT) {
                $byId[$eid]['sent_count']++;
                if ($byId[$eid]['last_sent_at'] === null) {
                    $byId[$eid]['last_sent_at'] = $log->sent_at;
                    $delivery = is_array($log->meta['delivery'] ?? null) ? $log->meta['delivery'] : null;
                    $byId[$eid]['last_delivery'] = $delivery;
                    $byId[$eid]['sent_without_real_delivery'] = is_array($delivery) && ($delivery['real_delivery'] ?? true) === false;
                }
            } elseif ($log->status === OnlineCourseEnrollmentEmailLog::STATUS_QUEUED) {
                $byId[$eid]['has_queued'] = true;
            } elseif ($log->status === OnlineCourseEnrollmentEmailLog::STATUS_FAILED) {
                $byId[$eid]['has_failed'] = true;
                if ($byId[$eid]['last_error'] === null) {
                    $byId[$eid]['last_error'] = $log->error_message;
                }
            }
        }

        return $byId;
    }

    public function batchName(OnlineCourse $course): string
    {
        return 'online-course-migration-emails-course-'.$course->id;
    }

    /**
     * @return array{active: bool, state: string, batch_id: mixed, total: int, processed: int, pending: int, failed: int}
     */
    public function batchStatus(OnlineCourse $course): array
    {
        $connection = config('queue.batching.database');
        $batchTable = config('queue.batching.table', 'job_batches');

        $row = DB::connection($connection)->table($batchTable)
            ->where('name', $this->batchName($course))
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return [
                'active' => false,
                'state' => 'none',
                'batch_id' => null,
                'total' => 0,
                'processed' => 0,
                'pending' => 0,
                'failed' => 0,
            ];
        }

        $total = (int) ($row->total_jobs ?? 0);
        $pending = (int) ($row->pending_jobs ?? 0);
        $failed = (int) ($row->failed_jobs ?? 0);
        $processed = max(0, $total - $pending);

        $state = 'active';
        $active = true;
        if (! empty($row->cancelled_at)) {
            $state = 'cancelled';
            $active = false;
        } elseif (! empty($row->finished_at)) {
            $state = 'finished';
            $active = false;
        }

        return [
            'active' => $active,
            'state' => $state,
            'batch_id' => $row->id,
            'total' => $total,
            'processed' => $processed,
            'pending' => $pending,
            'failed' => $failed,
        ];
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function cancelBatch(OnlineCourse $course): array
    {
        $connection = config('queue.batching.database');
        $batchTable = config('queue.batching.table', 'job_batches');

        $row = DB::connection($connection)->table($batchTable)
            ->where('name', $this->batchName($course))
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->first();

        if (! $row) {
            return ['success' => false, 'message' => 'Brak aktywnej wysyłki.'];
        }

        $batch = Bus::findBatch($row->id);
        if ($batch && ! $batch->cancelled()) {
            $batch->cancel();
        }

        return ['success' => true];
    }

    public function activeAccessQuery(OnlineCourse $course): Builder
    {
        $nowUtc = Carbon::now('UTC');

        return $course->enrollments()->getQuery()
            ->where(function (Builder $inner) use ($nowUtc) {
                $inner->whereNull('access_expires_at')
                    ->orWhere('access_expires_at', '>=', $nowUtc);
            });
    }

    public function eligibleForBulkQuery(OnlineCourse $course, string $mode): Builder
    {
        $query = $this->activeAccessQuery($course)
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($mode === 'unsent') {
            $query->whereNotExists(function ($q) use ($course) {
                $q->selectRaw('1')
                    ->from('online_course_enrollment_email_logs')
                    ->whereColumn('online_course_enrollment_email_logs.online_course_enrollment_id', 'online_course_enrollments.id')
                    ->where('online_course_enrollment_email_logs.online_course_id', $course->id)
                    ->where('online_course_enrollment_email_logs.type', OnlineCourseEnrollmentEmailLog::TYPE_PLATFORM_MIGRATION)
                    ->where('online_course_enrollment_email_logs.status', OnlineCourseEnrollmentEmailLog::STATUS_SENT);
            });
        }

        return $query;
    }
}
