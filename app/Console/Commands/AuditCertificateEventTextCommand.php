<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Services\Certificate\CertificatePdfRetentionService;
use Illuminate\Console\Command;

class AuditCertificateEventTextCommand extends Command
{
    protected $signature = 'certificates:audit-event-text
                            {--since=2026-07-08 : Od kiedy PDF-y renderują event_text bez automatycznej daty (wdrożenie 9a8c4b4)}
                            {--templates= : ID szablonów po przecinku (domyślnie: szablony, których event_text nie ma żadnej zmiennej)}
                            {--list : Wypisz pojedyncze zaświadczenia}
                            {--delete-files : Usuń PDF-y z uciętym tekstem (odtworzą się przy pobraniu); bez tej opcji tylko odczyt}
                            {--force : Nie pytaj o potwierdzenie przy --delete-files}';

    protected $description = 'Zaświadczenia z szablonów, w których „zorganizowanym w dniu” drukuje się bez daty (domyślnie tylko odczyt)';

    public function handle(CertificatePdfRetentionService $retention): int
    {
        $since = (string) $this->option('since');
        $templateIds = $this->option('templates')
            ? array_map('intval', explode(',', (string) $this->option('templates')))
            : $this->detectTemplatesWithoutVariables();

        if ($templateIds === []) {
            $this->info('Brak szablonów z event_text bez zmiennych. Po poprawce szablonów podaj --templates=2,3,6,7,8,9.');

            return self::SUCCESS;
        }

        $this->info('Szablony: '.implode(', ', $templateIds).". Okres od: {$since}.");

        $courseIds = Course::whereIn('certificate_template_id', $templateIds)->pluck('id');
        $base = Certificate::whereIn('course_id', $courseIds);

        $filesQuery = (clone $base)
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->where('generated_at', '>=', $since);
        $downloadedQuery = (clone $base)->where('last_downloaded_at', '>=', $since);

        $this->line('  kursy: '.$courseIds->count().', zaświadczenia: '.(clone $base)->count());
        $this->line('  PDF na dysku wygenerowane od '.$since.' (z uciętym tekstem): '.(clone $filesQuery)->count());
        $this->line('  zaświadczenia pobrane od '.$since.' (uczestnik mógł dostać ucięty tekst): '.(clone $downloadedQuery)->count());

        if ($this->option('list')) {
            $rows = (clone $base)
                ->where(fn ($q) => $q
                    ->where(fn ($q) => $q->whereNotNull('file_path')->where('file_path', '!=', '')->where('generated_at', '>=', $since))
                    ->orWhere('last_downloaded_at', '>=', $since))
                ->orderBy('course_id')
                ->orderBy('id')
                ->get(['id', 'course_id', 'certificate_number', 'file_path', 'generated_at', 'last_downloaded_at', 'download_count']);

            $this->table(
                ['id', 'kurs', 'numer', 'plik', 'generated_at', 'ostatnie pobranie', 'pobrań'],
                $rows->map(fn (Certificate $c) => [
                    $c->id,
                    $c->course_id,
                    $c->certificate_number,
                    $c->file_path ? 'tak' : '—',
                    $c->generated_at?->format('Y-m-d H:i'),
                    $c->last_downloaded_at?->format('Y-m-d H:i'),
                    $c->download_count,
                ])->all()
            );
        }

        if ($this->option('delete-files')) {
            $ids = (clone $filesQuery)->pluck('id');

            if ($ids->isEmpty()) {
                $this->info('Brak plików do usunięcia.');

                return self::SUCCESS;
            }

            if (! $this->option('force') && ! $this->confirm("Usunąć {$ids->count()} plików PDF? Rekordy i numery zostają.")) {
                return self::SUCCESS;
            }

            $stats = $retention->purgeFiles($ids);
            $this->info("Usunięto plików: {$stats['deleted']}, brak na dysku (wyzerowano file_path): {$stats['missing_on_disk']}, pominięte ścieżki: {$stats['skipped_invalid_path']}, błędy: {$stats['failed']}.");

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function detectTemplatesWithoutVariables(): array
    {
        return CertificateTemplate::orderBy('id')->get()
            ->filter(function (CertificateTemplate $template): bool {
                $config = is_array($template->config) ? $template->config : (json_decode((string) $template->config, true) ?? []);

                foreach ($config['blocks'] ?? [] as $block) {
                    if (($block['type'] ?? null) !== 'course_info') {
                        continue;
                    }
                    $eventText = $block['config']['event_text'] ?? null;

                    return is_string($eventText) && trim($eventText) !== '' && ! preg_match('/\{[a-z0-9_]+\}/i', $eventText);
                }

                return false;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
