<?php

namespace App\Jobs;

use App\Mail\CourseAccessMail;
use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\CourseFileLink;
use App\Models\CourseSurveyLink;
use App\Models\CourseVideo;
use App\Models\Participant;
use App\Models\ParticipantDownloadToken;
use App\Models\PneduUser;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCourseAccessEmailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public int $courseId,
        public int $participantId,
        public int $emailLogId
    ) {}

    public function handle(): void
    {
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

        $email = trim((string) ($participant->email ?? ''));
        if ($email === '') {
            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Brak adresu e-mail.',
            ]);

            return;
        }

        $hasVideos = CourseVideo::query()->where('course_id', $course->id)->exists();
        $hasMaterials = CourseFileLink::query()->where('course_id', $course->id)->exists();
        $hasCertificate = ($course->certificate_download_status === 'download_enabled');

        if (! $hasVideos && ! $hasMaterials && ! $hasCertificate) {
            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Brak nagrań, materiałów i aktywnego zaświadczenia dla kursu.',
                'meta' => [
                    'has_videos' => false,
                    'has_materials' => false,
                    'has_certificate' => false,
                ],
            ]);

            return;
        }

        $pneduFrontendUrl = rtrim(config('services.pnedu_frontend_url', 'http://localhost:8081'), '/');
        $courseUrl = $pneduFrontendUrl.'/dashboard/szkolenia/'.rawurlencode((string) $participant->id).'/wideo';

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

        try {
            Mail::to($email)->send(new CourseAccessMail(
                participant: $participant,
                course: $course,
                hasPneduAccount: $hasPneduAccount,
                courseUrl: $hasPneduAccount ? $courseUrl : null,
                certificateUrl: $certificateUrl,
                registerUrl: $registerUrl,
                participantEmail: $email,
                hasVideos: $hasVideos,
                hasMaterials: $hasMaterials,
                hasCertificate: $hasCertificate,
                surveyLinks: $surveyLinks
            ));

            $log->update([
                'status' => CertificateEmailLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
                'meta' => [
                    'has_videos' => $hasVideos,
                    'has_materials' => $hasMaterials,
                    'has_certificate' => $hasCertificate,
                    'pnedu_course_url' => $hasPneduAccount ? $courseUrl : null,
                    'pnedu_participant_id' => (int) $participant->id,
                    'has_pnedu_account' => $hasPneduAccount,
                    'certificate_url_included' => $certificateUrl !== null,
                    'register_url_included' => ! $hasPneduAccount,
                    'survey_links_count' => count($surveyLinks),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('SendCourseAccessEmailJob failed', [
                'course_id' => $this->courseId,
                'participant_id' => $this->participantId,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
                'meta' => [
                    'has_videos' => $hasVideos,
                    'has_materials' => $hasMaterials,
                    'has_certificate' => $hasCertificate,
                    'has_pnedu_account' => $hasPneduAccount,
                    'survey_links_count' => count($surveyLinks),
                ],
            ]);

            throw $e;
        }
    }
}
