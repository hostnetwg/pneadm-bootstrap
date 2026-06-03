<?php

namespace App\Console\Commands;

use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnableArchivedCertificateDownload extends Command
{
    protected $signature = 'courses:enable-archived-certificate-download
                            {--dry-run : Podgląd bez zapisu do bazy}
                            {--course= : Ogranicz do jednego ID kursu (np. 508)}
                            {--min-description=20 : Min. liczba znaków opisu po strip_tags i trim}
                            {--batch=500 : Rozmiar batcha przy zapisie}';

    protected $description = 'Jednorazowo ustawia certificate_download_status=download_enabled dla archiwalnych kursów z opisem (zagadnienia na zaświadczenie)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $minDescription = max(1, (int) $this->option('min-description'));
        $courseId = $this->option('course');
        $batchSize = max(1, (int) $this->option('batch'));
        $now = Carbon::now();

        if ($dryRun) {
            $this->warn('Tryb dry-run – żadne zmiany nie będą zapisane.');
        }

        $query = Course::query()
            ->where('certificate_download_status', 'in_preparation')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $now)
            ->whereNotNull('description')
            ->where('description', '!=', '');

        if ($courseId !== null && $courseId !== '') {
            $query->where('id', (int) $courseId);
        }

        $candidates = $query
            ->orderBy('id')
            ->get(['id', 'title', 'end_date', 'certificate_download_status', 'description']);

        $toUpdate = $candidates->filter(function (Course $course) use ($minDescription) {
            return $this->descriptionLength($course->description) >= $minDescription;
        })->values();

        $skippedShort = $candidates->count() - $toUpdate->count();

        $this->info("Kryteria: archiwalne (end_date < teraz), status=in_preparation, opis >= {$minDescription} znaków po oczyszczeniu.");
        $this->info("Kandydaci po filtrze SQL: {$candidates->count()}, do aktualizacji: {$toUpdate->count()}, pominięci (za krótki opis): {$skippedShort}.");

        if ($toUpdate->isEmpty()) {
            $this->warn('Brak kursów spełniających kryteria.');

            return self::SUCCESS;
        }

        $rows = $toUpdate->map(fn (Course $course) => [
            $course->id,
            Str::limit(strip_tags((string) $course->title), 60),
            $course->end_date?->format('Y-m-d H:i'),
            $this->descriptionLength($course->description),
        ]);

        $this->table(['ID', 'Tytuł', 'Koniec', 'Znaki opisu'], $rows->all());

        if ($dryRun) {
            $this->warn('Uruchom bez --dry-run, aby zapisać zmiany.');
            $this->line('IDs: '.$toUpdate->pluck('id')->implode(', '));

            return self::SUCCESS;
        }

        $ids = $toUpdate->pluck('id')->all();
        $logPath = storage_path('logs/certificate-download-status-backfill-'.now()->format('Y-m-d_His').'.txt');
        file_put_contents($logPath, "IDs przed UPDATE (rollback: SET in_preparation WHERE id IN (...)):\n".implode(',', $ids)."\n");

        $updated = 0;
        foreach (array_chunk($ids, $batchSize) as $chunk) {
            $updated += DB::table('courses')
                ->whereIn('id', $chunk)
                ->where('certificate_download_status', 'in_preparation')
                ->update([
                    'certificate_download_status' => 'download_enabled',
                    'updated_at' => now(),
                ]);
        }

        $this->info("Zaktualizowano rekordów: {$updated}.");
        $this->info("Log ID do rollbacku: {$logPath}");

        return self::SUCCESS;
    }

    private function descriptionLength(?string $description): int
    {
        if ($description === null) {
            return 0;
        }

        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($description)) ?? '');

        return mb_strlen($plain);
    }
}
