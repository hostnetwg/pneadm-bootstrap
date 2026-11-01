<?php

namespace App\Console\Commands;

use App\Services\Certificate\CertificatePdfRetentionService;
use Illuminate\Console\Command;

class CleanupCertificatePdfsCommand extends Command
{
    protected $signature = 'certificates:cleanup-pdfs
                            {--days= : Wiek pliku w dniach od generated_at (domyślnie config certificate_pdf_retention.days = 30)}
                            {--dry-run : Pokaż, co zostałoby usunięte, bez zmian}
                            {--limit=0 : Maks. liczba zaświadczeń w jednym uruchomieniu (0 = bez limitu)}
                            {--orphans : Zamiast tego usuń osierocone PDF-y (pliki bez file_path w bazie, starsze niż --days)}';

    protected $description = 'Usuwa stare pliki PDF zaświadczeń (można je odtworzyć z bazy) i zeruje file_path; rekordy i numery zostają';

    public function handle(CertificatePdfRetentionService $service): int
    {
        $days = $this->option('days') !== null && $this->option('days') !== ''
            ? (int) $this->option('days')
            : (int) config('certificate_pdf_retention.days', 30);
        $minDays = (int) config('certificate_pdf_retention.min_days', 7);

        if ($days < $minDays) {
            $this->error("--days musi wynosić co najmniej {$minDays} (podano {$days}).");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';

        if ($this->option('orphans')) {
            $stats = $service->cleanupOrphans($days, $dryRun);
            $this->info($prefix."Osierocone PDF-y (starsze niż {$days} dni): przeskanowano {$stats['scanned']}, do usunięcia/usunięto {$stats['deleted']}, błędy {$stats['failed']}, ".$this->mb($stats['bytes']).'.');

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $stats = $service->cleanupExpired($days, $dryRun, max(0, (int) $this->option('limit')));

        $this->info($prefix."Zaświadczenia z plikiem starszym niż {$days} dni: {$stats['candidates']}.");
        $this->line('  '.($dryRun ? 'pliki do usunięcia' : 'pliki usunięte').": {$stats['deleted']} (".$this->mb($stats['bytes']).')');
        $this->line('  pliku nie ma na dysku ('.($dryRun ? 'zostanie tylko wyzerowany' : 'wyzerowano tylko').") file_path: {$stats['missing_on_disk']}");
        $this->line('  issue_date '.($dryRun ? 'do zapisania' : 'zapisano').": z generated_at: {$stats['issue_date_frozen']}");
        $this->line("  pominięte (ścieżka poza certificates/<id>/*.pdf): {$stats['skipped_invalid_path']}");
        $this->line("  błędy kasowania: {$stats['failed']}");

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function mb(int $bytes): string
    {
        return number_format($bytes / 1048576, 1, ',', ' ').' MB';
    }
}
