<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseSeries;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EnableSeriesCertificateRegistration extends Command
{
    protected $signature = 'courses:enable-series-certificate-registration
                            {series=1 : ID serii kursów (np. 1 = TIK w pracy NAUCZYCIELA)}
                            {--dry-run : Podgląd bez zapisu do bazy}';

    protected $description = 'Włącza rejestrację zaświadczenia dla wszystkich kursów w serii: start = end_date, koniec = 23:59 tego samego dnia';

    public function handle(): int
    {
        $seriesId = (int) $this->argument('series');
        $dryRun = (bool) $this->option('dry-run');

        $series = CourseSeries::query()->with(['courses' => fn ($q) => $q->orderBy('courses.id')])->find($seriesId);
        if ($series === null) {
            $this->error("Nie znaleziono serii o ID {$seriesId}.");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Tryb dry-run – żadne zmiany nie będą zapisane.');
        }

        $this->info("Seria: {$series->name} (ID {$series->id})");

        $courses = $series->courses;
        if ($courses->isEmpty()) {
            $this->warn('Brak kursów przypisanych do tej serii.');

            return self::SUCCESS;
        }

        $tz = config('app.timezone');
        $rows = [];
        $skipped = [];
        $updated = 0;

        foreach ($courses as $course) {
            if ($course->end_date === null) {
                $skipped[] = [$course->id, Str::limit(strip_tags((string) $course->title), 50), 'brak end_date'];

                continue;
            }

            $end = Carbon::parse($course->end_date)->timezone($tz);
            $startsAt = $end->copy();
            $endsAt = $end->copy()->setTime(23, 59, 0);

            $willGenerateToken = trim((string) ($course->certificate_registration_token ?? '')) === '';
            $tokenNote = $willGenerateToken ? 'nowy token' : 'zachowany token';

            $rows[] = [
                $course->id,
                Str::limit(strip_tags((string) $course->title), 45),
                $startsAt->format('Y-m-d H:i'),
                $endsAt->format('Y-m-d H:i'),
                $course->certificate_registration_open ? 'tak' : 'nie → tak',
                $tokenNote,
            ];

            if ($dryRun) {
                continue;
            }

            $course->certificate_registration_open = true;
            $course->certificate_registration_starts_at = $startsAt;
            $course->certificate_registration_ends_at = $endsAt;
            if ($willGenerateToken) {
                $course->certificate_registration_token = Str::random(64);
            }
            $course->save();
            $updated++;
        }

        if ($rows !== []) {
            $this->table(
                ['ID', 'Tytuł', 'Rej. od', 'Rej. do', 'Było wł.', 'Token'],
                $rows,
            );
        }

        if ($skipped !== []) {
            $this->warn('Pominięte (brak daty zakończenia):');
            $this->table(['ID', 'Tytuł', 'Powód'], $skipped);
        }

        if ($dryRun) {
            $this->warn('Uruchom bez --dry-run, aby zapisać zmiany.');
            $this->line('Liczba kursów do aktualizacji: '.count($rows));
        } else {
            $this->info("Zaktualizowano kursów: {$updated}.");
        }

        return self::SUCCESS;
    }
}
