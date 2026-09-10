<?php

namespace App\Jobs;

use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use App\Models\OnlineCourseEnrollmentEmailLog;
use App\Services\Mail\SystemMailDiagnostics;
use App\Services\OnlineCourseEnrollmentPlatformMigrationMailService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOnlineCoursePlatformMigrationEmailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public int $onlineCourseId,
        public int $enrollmentId,
        public int $emailLogId
    ) {}

    public function handle(OnlineCourseEnrollmentPlatformMigrationMailService $mailService): void
    {
        $log = OnlineCourseEnrollmentEmailLog::query()->find($this->emailLogId);
        if (! $log) {
            return;
        }

        $enrollment = OnlineCourseEnrollment::query()->find($this->enrollmentId);
        $course = OnlineCourse::query()->find($this->onlineCourseId);

        if (! $enrollment || ! $course) {
            $log->update([
                'status' => OnlineCourseEnrollmentEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Brak zapisu lub kursu (albo nie pasują).',
            ]);

            return;
        }

        $built = $mailService->makeMailable($course, $enrollment);
        if (! ($built['success'] ?? false) || ! $built['mailable'] || ! $built['email']) {
            $log->update([
                'status' => OnlineCourseEnrollmentEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $built['error'] ?? 'Nie udało się przygotować wiadomości.',
            ]);

            return;
        }

        try {
            $deliveryMeta = app(SystemMailDiagnostics::class)->send($built['email'], $built['mailable']);

            $log->update([
                'status' => OnlineCourseEnrollmentEmailLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
                'meta' => [
                    'has_pnedu_account' => $built['has_pnedu_account'],
                    'pnedu_course_url' => $built['course_url'],
                    'register_url_included' => ! $built['has_pnedu_account'],
                    'delivery' => $deliveryMeta,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('SendOnlineCoursePlatformMigrationEmailJob failed', [
                'online_course_id' => $this->onlineCourseId,
                'enrollment_id' => $this->enrollmentId,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => OnlineCourseEnrollmentEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
                'meta' => [
                    'has_pnedu_account' => $built['has_pnedu_account'],
                ],
            ]);

            throw $e;
        }
    }
}
