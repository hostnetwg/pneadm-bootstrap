<?php

namespace App\Console\Commands;

use App\Jobs\SendAccessExpiryReminderEmailJob;
use App\Models\CertificateEmailLog;
use App\Services\ParticipantAccessExpiryReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendParticipantAccessExpiryRemindersCommand extends Command
{
    protected $signature = 'participants:send-access-expiry-reminders
                            {--days= : Tylko wybrany offset (np. 7 lub 1); domyślnie wszystkie z config}
                            {--date= : Data referencyjna Y-m-d (strefa config); domyślnie dziś}
                            {--dry-run : Pokaż kandydatów bez wysyłki}';

    protected $description = 'Wysyła przypomnienia e-mail o zbliżającym się wygaśnięciu dostępu do nagrań/materiałów (płatne szkolenia)';

    public function handle(ParticipantAccessExpiryReminderService $service): int
    {
        if (! config('participant_access.expiry_reminder.enabled', true)) {
            $this->warn('Przypomnienia wyłączone (PARTICIPANT_ACCESS_EXPIRY_REMINDERS_ENABLED=false).');

            return self::SUCCESS;
        }

        $tz = $service->timezone();
        $referenceDay = $this->option('date')
            ? Carbon::parse($this->option('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $daysList = $this->option('days') !== null
            ? [(int) $this->option('days')]
            : $service->configuredDaysBefore();

        if ($daysList === [] || $daysList === [0]) {
            $this->error('Brak skonfigurowanych offsetów (PARTICIPANT_ACCESS_EXPIRY_REMINDER_DAYS).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $queued = 0;

        $this->info('Data referencyjna: '.$referenceDay->toDateString().' ('.$tz.')');
        if ($dryRun) {
            $this->comment('Tryb dry-run — bez wysyłki.');
        }

        foreach ($daysList as $daysBefore) {
            if ($daysBefore < 1) {
                continue;
            }

            $participants = $service->participantsDueForReminder($daysBefore, $referenceDay);
            $this->line('');
            $this->info("Offset {$daysBefore} dni przed wygaśnięciem — kandydaci: ".$participants->count());

            foreach ($participants as $participant) {
                $expiresLocal = $participant->access_expires_at
                    ->copy()
                    ->setTimezone($tz)
                    ->format('d.m.Y H:i');

                $this->line(sprintf(
                    '  #%d %s <%s> | kurs #%d | wygasa: %s',
                    $participant->id,
                    trim($participant->first_name.' '.$participant->last_name),
                    $participant->email,
                    $participant->course_id,
                    $expiresLocal
                ));

                if ($dryRun) {
                    continue;
                }

                $log = CertificateEmailLog::create([
                    'course_id' => $participant->course_id,
                    'participant_id' => $participant->id,
                    'type' => CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER,
                    'status' => CertificateEmailLog::STATUS_QUEUED,
                    'created_by' => null,
                    'queued_at' => now(),
                    'meta' => [
                        'days_before' => $daysBefore,
                        'automated' => true,
                        'reference_date' => $referenceDay->toDateString(),
                    ],
                ]);

                SendAccessExpiryReminderEmailJob::dispatch(
                    (int) $participant->course_id,
                    (int) $participant->id,
                    $daysBefore,
                    (int) $log->id
                );

                $queued++;
            }
        }

        if (! $dryRun) {
            $this->info("Zlecono {$queued} wiadomości (kolejka).");
        }

        return self::SUCCESS;
    }
}
