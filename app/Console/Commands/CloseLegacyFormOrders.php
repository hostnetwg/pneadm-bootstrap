<?php

namespace App\Console\Commands;

use App\Services\FormOrderLegacyCloseService;
use Illuminate\Console\Command;

class CloseLegacyFormOrders extends Command
{
    protected $signature = 'form-orders:close-legacy-handled
                            {--dry-run : Tylko raport liczb i przykładowych ID, bez zapisu}
                            {--group=all : all | unlinked | archival}
                            {--sample=10 : Liczba przykładowych ID w podglądzie}';

    protected $description = 'Operacyjnie zamyka historyczne zamówienia (legacy import / archiwalne Publigo) — dev/prod po akceptacji dry-run';

    public function handle(FormOrderLegacyCloseService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $group = (string) $this->option('group');
        $sample = max(1, (int) $this->option('sample'));

        if (! in_array($group, ['all', 'unlinked', 'archival'], true)) {
            $this->error('Grupa musi być: all, unlinked lub archival.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Tryb dry-run — legacy_handled_at nie zostanie ustawione.');
        } else {
            $this->warn('Zapis w bazie — upewnij się, że to właściwe środowisko (dev/staging/prod).');
        }

        $preview = $service->previewCounts($sample);
        $this->table(
            ['Grupa', 'Liczba', 'Przykładowe ID'],
            [
                [
                    'unlinked (FV bez kursu)',
                    $preview['unlinked'],
                    implode(', ', $preview['sample_unlinked']) ?: '—',
                ],
                [
                    'archival (FV + po terminie + brak uczestnika)',
                    $preview['archival'],
                    implode(', ', $preview['sample_archival']) ?: '—',
                ],
            ]
        );

        $groups = $group === 'all' ? ['unlinked', 'archival'] : [$group];
        $total = 0;

        foreach ($groups as $g) {
            $result = $service->closeGroup($g, null, $dryRun);
            $verb = $dryRun ? 'Do zamknięcia' : 'Zamknięto';
            $this->info("{$verb} ({$g}): {$result['closed']}");
            $total += $result['closed'];
        }

        $this->info($dryRun
            ? "Razem do zamknięcia: {$total}. Uruchom bez --dry-run po akceptacji."
            : "Razem zamknięto operacyjnie: {$total}.");

        return self::SUCCESS;
    }
}
