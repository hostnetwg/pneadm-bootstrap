<?php

namespace App\Jobs;

use App\Mail\CourseAccessExpiryReminderMail;
use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\CourseFileLink;
use App\Models\CourseSurveyLink;
use App\Models\CourseVideo;
use App\Models\Participant;
use App\Models\ParticipantDownloadToken;
use App\Models\PneduUser;
use App\Services\Mail\SystemMailDiagnostics;
use App\Services\ParticipantAccessExpiryReminderService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAccessExpiryReminderEmailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public int $courseId,
        public int $participantId,
        public int $daysBefore,
        public int $emailLogId
    ) {}

    public function handle(
        ParticipantAccessExpiryReminderService $reminderService
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $log = CertificateEmailLog::find($this->emailLogId);
        if (! $log) {
            return;
        }

        $participant = Participant::find($this->participantId);
        $course = Course::find($this->courseId);

        if (! $participant || ! $course || (int) $participant->course_id !== (int) $course->id) {
            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Brak uczestnika lub kursu (albo nie pasują).',
            ]);

            return;
        }

        $isManual = (bool) (($log->meta['manual'] ?? false));

        if (! $isManual && $reminderService->reminderWasSent(
            (int) $participant->id,
            (int) $course->id,
            $this->daysBefore
        )) {
            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Przypomnienie dla tego offsetu zostało już wysłane.',
            ]);

            return;
        }

        $email = trim((string) ($participant->email ?? ''));
        if ($email === '') {
            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Brak adresu e-mail.',
            ]);

            return;
        }

        if (! $participant->access_expires_at || $participant->hasExpiredAccess()) {
            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Dostęp już wygasł lub brak daty wygaśnięcia.',
            ]);

            return;
        }

        $hasVideos = CourseVideo::query()->where('course_id', $course->id)->exists();
        $hasMaterials = CourseFileLink::query()->where('course_id', $course->id)->exists();

        if (! $hasVideos && ! $hasMaterials) {
            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Brak nagrań i materiałów — przypomnienie nie jest wysyłane.',
            ]);

            return;
        }

        $hasCertificate = ($course->certificate_download_status === 'download_enabled');
        $pneduFrontendUrl = rtrim(config('services.pnedu_frontend_url', 'http://localhost:8081'), '/');
        $courseUrl = $pneduFrontendUrl.'/dashboard/szkolenia/'.rawurlencode((string) $participant->id).'/wideo';

        $normalizedEmail = strtolower(trim($email));
        $hasPneduAccount = PneduUser::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->exists();

        $certificateUrl = null;
        if ($hasCertificate) {
            $token = ParticipantDownloadToken::getOrCreateTokenForEmail($email);
            $certificateUrl = $pneduFrontendUrl.'/certificate/'.$token.'/'.$course->id;
        }

        $registerUrl = $pneduFrontendUrl.'/register?email='.urlencode($email);

        $accessExpiresAtFormatted = $participant->access_expires_at
            ->copy()
            ->setTimezone('Europe/Warsaw')
            ->format('d.m.Y H:i');

        $surveyLinks = CourseSurveyLink::query()
            ->where('course_id', $course->id)
            ->availableNow()
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (CourseSurveyLink $link) => [
                'title' => trim((string) ($link->title ?? '')) !== '' ? trim((string) $link->title) : 'Ankieta szkoleniowa',
                'url' => $link->participantFacingSurveyUrl(),
            ])
            ->filter(fn (array $item) => trim((string) ($item['url'] ?? '')) !== '')
            ->values()
            ->all();

        try {
            $deliveryMeta = app(SystemMailDiagnostics::class)->send(
                $email,
                new CourseAccessExpiryReminderMail(
                    participant: $participant,
                    course: $course,
                    daysBefore: $this->daysBefore,
                    hasPneduAccount: $hasPneduAccount,
                    courseUrl: $hasPneduAccount ? $courseUrl : null,
                    certificateUrl: $certificateUrl,
                    registerUrl: $registerUrl,
                    participantEmail: $email,
                    hasVideos: $hasVideos,
                    hasMaterials: $hasMaterials,
                    hasCertificate: $hasCertificate,
                    accessExpiresAtFormatted: $accessExpiresAtFormatted,
                    surveyLinks: $surveyLinks
                )
            );

            $log->update([
                'status' => CertificateEmailLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
                'meta' => array_merge($log->meta ?? [], [
                    'days_before' => $this->daysBefore,
                    'access_expires_at' => $participant->access_expires_at->toIso8601String(),
                    'has_videos' => $hasVideos,
                    'has_materials' => $hasMaterials,
                    'has_certificate' => $hasCertificate,
                    'delivery' => $deliveryMeta,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SendAccessExpiryReminderEmailJob failed', [
                'course_id' => $this->courseId,
                'participant_id' => $this->participantId,
                'days_before' => $this->daysBefore,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
                'meta' => array_merge($log->meta ?? [], [
                    'days_before' => $this->daysBefore,
                ]),
            ]);

            throw $e;
        }
    }
}
