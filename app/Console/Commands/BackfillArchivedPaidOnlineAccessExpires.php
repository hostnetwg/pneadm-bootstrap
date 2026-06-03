<?php

namespace App\Console\Commands;

use App\Models\Participant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillArchivedPaidOnlineAccessExpires extends Command
{
    protected $signature = 'participants:backfill-archived-access-expires
                            {--dry-run : Podgląd bez zapisu do bazy}
                            {--course= : Ogranicz do jednego course_id}
                            {--months=2 : Liczba miesięcy po courses.end_date}
                            {--batch=500 : Rozmiar batcha przy zapisie}';

    protected $description = 'Ustawia access_expires_at (end_date + N mies.) dla uczestników płatnych szkoleń online archiwalnych bez daty dostępu';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $months = max(1, (int) $this->option('months'));
        $courseId = $this->option('course');
        $batchSize = max(1, (int) $this->option('batch'));
        $now = Carbon::now();

        if ($dryRun) {
            $this->warn('Tryb dry-run – żadne zmiany nie będą zapisane.');
        }

        $query = Participant::query()
            ->whereNull('access_expires_at')
            ->whereHas('course', function ($q) use ($now, $courseId) {
                $q->where('is_paid', true)
                    ->where('type', 'online')
                    ->whereNotNull('end_date')
                    ->where('end_date', '<', $now);

                if ($courseId !== null && $courseId !== '') {
                    $q->where('id', (int) $courseId);
                }
            })
            ->with(['course:id,title,end_date,is_paid,type'])
            ->orderBy('course_id')
            ->orderBy('id');

        $participants = $query->get(['id', 'course_id', 'email', 'first_name', 'last_name', 'access_expires_at']);

        $rows = [];
        $updates = [];

        foreach ($participants as $participant) {
            $course = $participant->course;
            if (! $course || ! $course->end_date) {
                continue;
            }

            $newExpiresAt = Carbon::parse($course->end_date)->copy()->addMonths($months);

            $updates[] = [
                'id' => $participant->id,
                'course_id' => $course->id,
                'expires_at' => $newExpiresAt,
            ];

            $rows[] = [
                $participant->id,
                $course->id,
                Str::limit(strip_tags((string) $course->title), 45),
                $course->end_date->format('Y-m-d H:i'),
                $newExpiresAt->format('Y-m-d H:i'),
                Str::limit($participant->email ?? '', 35),
            ];
        }

        $this->info("Kryteria: płatne + online + archiwalne (end_date < teraz), access_expires_at IS NULL, +{$months} mies. od end_date.");
        $this->info('Do aktualizacji: '.count($updates).' uczestników.');

        if ($updates === []) {
            $this->warn('Brak uczestników spełniających kryteria.');

            return self::SUCCESS;
        }

        $this->table(
            ['Part. ID', 'Kurs ID', 'Tytuł', 'Koniec szkolenia', 'Nowy dostęp do', 'E-mail'],
            array_slice($rows, 0, 50)
        );

        if (count($rows) > 50) {
            $this->line('… i '.(count($rows) - 50).' kolejnych (pełna lista w logu przy zapisie).');
        }

        if ($dryRun) {
            $this->warn('Uruchom bez --dry-run, aby zapisać zmiany.');
            $this->line('Participant IDs (pierwsze 30): '.collect($updates)->take(30)->pluck('id')->implode(', '));

            return self::SUCCESS;
        }

        $logPath = storage_path('logs/participants-access-expires-backfill-'.now()->format('Y-m-d_His').'.txt');
        $logLines = ["participant_id\tcourse_id\tnew_access_expires_at\n"];
        foreach ($updates as $row) {
            $logLines[] = $row['id']."\t".$row['course_id']."\t".$row['expires_at']->format('Y-m-d H:i:s')."\n";
        }
        file_put_contents($logPath, implode('', $logLines));

        $updated = 0;
        foreach (array_chunk($updates, $batchSize) as $chunk) {
            DB::transaction(function () use ($chunk, &$updated) {
                foreach ($chunk as $row) {
                    $count = DB::table('participants')
                        ->where('id', $row['id'])
                        ->whereNull('access_expires_at')
                        ->update([
                            'access_expires_at' => $row['expires_at'],
                            'updated_at' => now(),
                        ]);
                    $updated += $count;
                }
            });
        }

        $this->info("Zaktualizowano rekordów: {$updated}.");
        $this->info("Log (rollback: SET access_expires_at = NULL WHERE id IN (...)): {$logPath}");

        return self::SUCCESS;
    }
}
