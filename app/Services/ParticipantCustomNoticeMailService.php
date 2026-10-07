<?php

namespace App\Services;

use App\Mail\ParticipantCustomNoticeMail;
use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\Participant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ParticipantCustomNoticeMailService
{
    public function defaultSubject(Course $course): string
    {
        $subject = 'Awaria platformy ClickMeeting — '.$course->plainTitle();

        if (mb_strlen($subject) > 180) {
            return mb_substr($subject, 0, 177).'…';
        }

        return $subject;
    }

    public function defaultBody(Course $course): string
    {
        $title = $course->plainTitle();

        return <<<TXT
Dzień dobry,

informujemy, że platforma ClickMeeting ma obecnie awarię. Z tego powodu dołączenie do spotkania na żywo może być w tej chwili niemożliwe.

Śledzimy sytuację i wyślemy kolejną wiadomość, gdy platforma zacznie działać albo gdy ustalimy inny sposób przeprowadzenia szkolenia.

Szkolenie: {$title}

Pozdrawiamy
Platforma Nowoczesnej Edukacji
TXT;
    }

    public function recipientCount(Course $course): int
    {
        return $this->recipients($course)['recipients']->count();
    }

    /**
     * Jedna wiadomość na unikalny, poprawny adres. Kolejność: rosnąco po id uczestnika.
     *
     * @return array{recipients: Collection<int, Participant>, skipped_invalid: int, skipped_duplicate: int}
     */
    public function recipients(Course $course): array
    {
        $participants = Participant::query()
            ->where('course_id', $course->id)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->get(['id', 'course_id', 'email', 'first_name', 'last_name']);

        $seen = [];
        $recipients = collect();
        $skippedInvalid = 0;
        $skippedDuplicate = 0;

        foreach ($participants as $participant) {
            $email = mb_strtolower(trim((string) $participant->email));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $skippedInvalid++;

                continue;
            }

            if (isset($seen[$email])) {
                $skippedDuplicate++;

                continue;
            }

            $seen[$email] = true;
            $recipients->push($participant);
        }

        return [
            'recipients' => $recipients,
            'skipped_invalid' => $skippedInvalid,
            'skipped_duplicate' => $skippedDuplicate,
        ];
    }

    /**
     * Wysyłka synchroniczna — maile wychodzą w tym żądaniu, bez workera kolejki.
     *
     * @return array{sent: int, failed: int, skipped_invalid: int, skipped_duplicate: int, first_error: ?string}
     */
    public function send(Course $course, string $subject, string $body, ?int $createdBy): array
    {
        set_time_limit(0);

        $selection = $this->recipients($course);
        $sent = 0;
        $failed = 0;
        $firstError = null;

        foreach ($selection['recipients'] as $participant) {
            $email = trim((string) $participant->email);
            $log = CertificateEmailLog::create([
                'course_id' => $course->id,
                'participant_id' => $participant->id,
                'type' => CertificateEmailLog::TYPE_CUSTOM_NOTICE,
                'status' => CertificateEmailLog::STATUS_QUEUED,
                'created_by' => $createdBy,
                'queued_at' => now(),
                'meta' => [
                    'subject' => $subject,
                    'manual' => true,
                    'bulk' => true,
                ],
            ]);

            try {
                Mail::to($email)->send(new ParticipantCustomNoticeMail($course, $body, $subject));
                $log->update([
                    'status' => CertificateEmailLog::STATUS_SENT,
                    'sent_at' => now(),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $firstError ??= $e->getMessage();
                $log->update([
                    'status' => CertificateEmailLog::STATUS_FAILED,
                    'failed_at' => now(),
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                ]);
                Log::warning('Participant custom notice failed', [
                    'course_id' => $course->id,
                    'participant_id' => $participant->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped_invalid' => $selection['skipped_invalid'],
            'skipped_duplicate' => $selection['skipped_duplicate'],
            'first_error' => $firstError,
        ];
    }
}
