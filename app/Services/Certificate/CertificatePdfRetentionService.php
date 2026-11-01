<?php

namespace App\Services\Certificate;

use App\Models\Certificate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Retencja plików PDF zaświadczeń (storage/app/public/certificates/<id>/*.pdf).
 *
 * Zasady bezpieczeństwa:
 * - kasujemy WYŁĄCZNIE ścieżki certificates/<liczba>/*.pdf i certificates/online-<liczba>/*.pdf
 *   (katalogi logos/, backgrounds/ i inne zasoby szablonów są nietykalne),
 * - rekordy i numery zaświadczeń zostają; zerujemy tylko file_path (jak ręczne "Usuń PDF"),
 * - przed skasowaniem "zamrażamy" datę wystawienia (issue_date = data generated_at), jeśli nigdzie jej
 *   nie ma, żeby odtworzony PDF nie dostał dzisiejszej daty,
 * - wiek pliku liczymy z generated_at (nie z pobrań); brak generated_at = nie ruszamy.
 */
class CertificatePdfRetentionService
{
    /** Dozwolone ścieżki względem dysku 'public'. */
    public const PATH_PATTERN = '#^certificates/(?:online-)?\d+/[^/]+\.pdf$#';

    /** Dozwolone nazwy podkatalogów w certificates/. */
    private const DIR_PATTERN = '/^(?:online-)?\d+$/';

    /**
     * @return array{candidates:int,deleted:int,missing_on_disk:int,skipped_invalid_path:int,failed:int,issue_date_frozen:int,bytes:int}
     */
    public function cleanupExpired(int $days, bool $dryRun = false, int $limit = 0): array
    {
        $cutoff = now()->subDays($days);
        $disk = Storage::disk('public');
        $stats = [
            'candidates' => 0,
            'deleted' => 0,
            'missing_on_disk' => 0,
            'skipped_invalid_path' => 0,
            'failed' => 0,
            'issue_date_frozen' => 0,
            'bytes' => 0,
        ];

        Certificate::query()
            ->with(['course:id,issue_date_certyficates', 'onlineCourse:id,certificate_issue_date'])
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->whereNotNull('generated_at')
            ->where('generated_at', '<', $cutoff)
            ->chunkById(200, function ($certificates) use (&$stats, $disk, $dryRun, $limit) {
                foreach ($certificates as $certificate) {
                    if ($limit > 0 && $stats['candidates'] >= $limit) {
                        return false;
                    }

                    $relative = $this->relativePath((string) $certificate->file_path);
                    if ($relative === null) {
                        $stats['skipped_invalid_path']++;

                        continue;
                    }

                    $stats['candidates']++;
                    $exists = $disk->exists($relative);
                    $size = $exists ? (int) $disk->size($relative) : 0;
                    $freeze = $this->needsIssueDateFreeze($certificate);

                    if ($dryRun) {
                        $exists ? $stats['deleted']++ : $stats['missing_on_disk']++;
                        $stats['bytes'] += $size;
                        $stats['issue_date_frozen'] += $freeze ? 1 : 0;

                        continue;
                    }

                    if ($exists && ! $disk->delete($relative)) {
                        $stats['failed']++;
                        Log::warning('certificates:cleanup-pdfs: nie udało się skasować pliku', ['certificate_id' => $certificate->id, 'path' => $relative]);

                        continue;
                    }

                    $update = ['file_path' => null];
                    if ($freeze) {
                        $update['issue_date'] = $certificate->generated_at->toDateString();
                        $stats['issue_date_frozen']++;
                    }
                    DB::table('certificates')->where('id', $certificate->id)->update($update);

                    $exists ? $stats['deleted']++ : $stats['missing_on_disk']++;
                    $stats['bytes'] += $size;
                }

                return true;
            });

        if (! $dryRun) {
            Log::info('certificates:cleanup-pdfs: zakończono', $stats + ['days' => $days]);
        }

        return $stats;
    }

    /**
     * Pliki PDF w certificates/<id>/ bez odpowiadającego file_path w bazie i starsze niż $days (mtime).
     *
     * @return array{scanned:int,orphans:int,deleted:int,failed:int,bytes:int}
     */
    public function cleanupOrphans(int $days, bool $dryRun = false): array
    {
        $disk = Storage::disk('public');
        $cutoffTs = now()->subDays($days)->getTimestamp();
        $stats = ['scanned' => 0, 'orphans' => 0, 'deleted' => 0, 'failed' => 0, 'bytes' => 0];

        foreach ($disk->directories('certificates') as $dir) {
            if (! preg_match(self::DIR_PATTERN, basename($dir))) {
                continue; // logos, backgrounds itp.
            }

            $referenced = Certificate::query()
                ->where('file_path', 'like', 'storage/'.$dir.'/%')
                ->pluck('file_path')
                ->mapWithKeys(fn ($path) => [$this->relativePath((string) $path) => true])
                ->all();

            foreach ($disk->files($dir) as $file) {
                if (! preg_match(self::PATH_PATTERN, $file)) {
                    continue;
                }
                $stats['scanned']++;

                if (isset($referenced[$file]) || $disk->lastModified($file) >= $cutoffTs) {
                    continue;
                }

                $stats['orphans']++;
                $size = (int) $disk->size($file);

                if ($dryRun) {
                    $stats['deleted']++;
                    $stats['bytes'] += $size;

                    continue;
                }

                if ($disk->delete($file)) {
                    $stats['deleted']++;
                    $stats['bytes'] += $size;
                } else {
                    $stats['failed']++;
                }
            }
        }

        if (! $dryRun) {
            Log::info('certificates:cleanup-pdfs --orphans: zakończono', $stats + ['days' => $days]);
        }

        return $stats;
    }

    /** Ścieżka względem dysku 'public' albo null, gdy poza dozwolonym wzorcem. */
    public function relativePath(string $filePath): ?string
    {
        $relative = Str::startsWith($filePath, 'storage/') ? Str::after($filePath, 'storage/') : $filePath;

        return preg_match(self::PATH_PATTERN, $relative) ? $relative : null;
    }

    /**
     * Czy trzeba zapisać issue_date, bo przy odtwarzaniu PDF generator nie znalazłby żadnej daty
     * (certificates.issue_date, online_courses.certificate_issue_date, courses.issue_date_certyficates).
     */
    private function needsIssueDateFreeze(Certificate $certificate): bool
    {
        if ($certificate->issue_date !== null || $certificate->generated_at === null) {
            return false;
        }

        if ($certificate->online_course_id !== null) {
            return $certificate->onlineCourse?->certificate_issue_date === null;
        }

        return $certificate->course?->issue_date_certyficates === null;
    }
}
