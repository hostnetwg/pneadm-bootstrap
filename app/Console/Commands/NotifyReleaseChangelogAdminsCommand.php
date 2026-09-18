<?php

namespace App\Console\Commands;

use App\Services\ReleaseChangelogNotifyService;
use Illuminate\Console\Command;

class NotifyReleaseChangelogAdminsCommand extends Command
{
    protected $signature = 'changelog:notify-admins
                            {app : adm albo pnedu}
                            {--dry-run : Pokaż odbiorców bez wysyłki}';

    protected $description = 'Mail o nowym numerze wersji do aktywnych adminów i superadminów. Nie używać przy hotfixie. Najpierw decyzja Waldemara.';

    public function handle(ReleaseChangelogNotifyService $notify): int
    {
        $app = strtolower(trim((string) $this->argument('app')));
        $dryRun = (bool) $this->option('dry-run');

        $result = $notify->notify($app, $dryRun);

        if ($result['error'] !== null) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info(($result['dry_run'] ? 'Dry-run' : 'Wysyłka').' — '.$app.' v '.($result['version'] ?? '—'));
        foreach ($result['recipients'] as $email) {
            $this->line('  '.$email);
        }
        $this->info('Odbiorców: '.count($result['recipients']).($result['dry_run'] ? ' (bez wysyłki)' : ', wysłano: '.$result['sent']));

        return self::SUCCESS;
    }
}
