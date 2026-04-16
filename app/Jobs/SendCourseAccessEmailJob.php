<?php

namespace App\Jobs;

use App\Mail\CourseAccessMail;
use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\CourseFileLink;
use App\Models\CourseVideo;
use App\Models\Participant;
use App\Models\PneduUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SendCourseAccessEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $pneduCourseId = $this->resolvePneduCourseId($course);
        $courseUrl = $pneduFrontendUrl.'/dashboard/szkolenia/'.rawurlencode((string) $pneduCourseId).'/wideo';

        $normalizedEmail = strtolower(trim($email));
        $pneduUser = PneduUser::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->first();

        $accountCreatedNow = false;
        $setPasswordUrl = null;

        try {
            if (! $pneduUser) {
                $accountCreatedNow = true;
                $pneduUser = PneduUser::query()->create([
                    'first_name' => trim((string) ($participant->first_name ?? '')),
                    'last_name' => trim((string) ($participant->last_name ?? '')),
                    'email' => $normalizedEmail,
                    'password' => Hash::make(Str::password(48)),
                    'email_verified_at' => now(),
                ]);
            }

            if ($accountCreatedNow) {
                $token = Password::broker('pnedu_users')->createToken($pneduUser);
                $setPasswordUrl = $pneduFrontendUrl.'/reset-password/'.$token.'?email='.urlencode($pneduUser->getEmailForPasswordReset());
            }

            Mail::to($email)->send(new CourseAccessMail(
                participant: $participant,
                course: $course,
                courseUrl: $courseUrl,
                hasVideos: $hasVideos,
                hasMaterials: $hasMaterials,
                hasCertificate: $hasCertificate,
                accountCreatedNow: $accountCreatedNow,
                setPasswordUrl: $setPasswordUrl
            ));

            $log->update([
                'status' => CertificateEmailLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
                'meta' => [
                    'has_videos' => $hasVideos,
                    'has_materials' => $hasMaterials,
                    'has_certificate' => $hasCertificate,
                    'pnedu_course_url' => $courseUrl,
                    'pnedu_course_id' => (string) $pneduCourseId,
                    'account_created_now' => $accountCreatedNow,
                    'set_password_url_generated' => $setPasswordUrl !== null,
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
                    'pnedu_course_url' => $courseUrl,
                    'account_created_now' => $accountCreatedNow,
                ],
            ]);

            throw $e;
        }
    }

    private function resolvePneduCourseId(Course $course): string
    {
        $idOld = trim((string) ($course->id_old ?? ''));
        if ($idOld !== '') {
            return $idOld;
        }

        return (string) $course->id;
    }
}
