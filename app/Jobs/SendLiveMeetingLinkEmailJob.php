<?php

namespace App\Jobs;

use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\Participant;
use App\Services\ParticipantLiveMeetingLinkMailService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLiveMeetingLinkEmailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public int $courseId,
        public int $participantId,
        public int $emailLogId
    ) {}

    public function handle(ParticipantLiveMeetingLinkMailService $mailService): void
    {
        $log = CertificateEmailLog::find($this->emailLogId);
        if (! $log) {
            return;
        }

        $participant = Participant::find($this->participantId);
        $course = Course::with(['onlineDetails', 'instructor'])->find($this->courseId);

        if (! $participant || ! $course) {
            $log->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Brak uczestnika lub kursu.',
            ]);

            return;
        }

        $result = $mailService->sendToParticipant($course, $participant, $log);

        if (! ($result['success'] ?? false)) {
            Log::warning('SendLiveMeetingLinkEmailJob failed', [
                'course_id' => $this->courseId,
                'participant_id' => $this->participantId,
                'error' => $result['error'] ?? 'unknown',
            ]);

            throw new \RuntimeException((string) ($result['error'] ?? 'Nie udało się wysłać e-maila z linkiem live.'));
        }
    }
}
