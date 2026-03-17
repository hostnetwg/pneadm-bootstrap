<?php

namespace App\Jobs;

use App\Mail\CertificateLinkMail;
use App\Mail\CertificateSingleLinkMail;
use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\Participant;
use App\Models\ParticipantDownloadToken;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCertificateLinkEmailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $courseId,
        public int $participantId,
        public string $type,
        public int $emailLogId
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $log = CertificateEmailLog::find($this->emailLogId);
        if (!$log) {
            return;
        }

        $participant = Participant::find($this->participantId);
        $course = Course::with('instructor')->find($this->courseId);

        if (!$participant || !$course || (int) $participant->course_id !== (int) $course->id) {
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

        $token = ParticipantDownloadToken::getOrCreateTokenForEmail($email);
        $pneduFrontendUrl = rtrim(config('services.pnedu_frontend_url', 'http://localhost:8081'), '/');

        try {
            if ($this->type === CertificateEmailLog::TYPE_SINGLE_CERTIFICATE) {
                $trainerName = null;
                if (!empty($course->trainer) && $course->trainer !== 'Brak trenera') {
                    $trainerName = $course->trainer;
                } elseif ($course->relationLoaded('instructor') && $course->instructor) {
                    $trainerName = trim(($course->instructor->first_name ?? '') . ' ' . ($course->instructor->last_name ?? '')) ?: null;
                }

                $certificateUrl = $pneduFrontendUrl . '/certificate/' . $token . '/' . $course->id;
                Mail::to($email)->send(new CertificateSingleLinkMail($participant, $course, $certificateUrl, $trainerName));
            } else {
                $certificatesUrl = $pneduFrontendUrl . '/certificates/' . $token;
                Mail::to($email)->send(new CertificateLinkMail($participant, $course, $certificatesUrl));
            }

            $log->update([
                'status' => CertificateEmailLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SendCertificateLinkEmailJob failed', [
                'course_id' => $this->courseId,
                'participant_id' => $this->participantId,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

