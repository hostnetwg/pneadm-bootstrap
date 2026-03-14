<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Models\ParticipantDownloadToken;
use Illuminate\Console\Command;

class BackfillParticipantDownloadTokens extends Command
{
    protected $signature = 'participants:backfill-download-tokens
                            {--batch=5000 : Liczba rekordów participants w jednym batchu}
                            {--dry-run : Tylko podsumowanie, bez zapisu}';

    protected $description = 'Uzupełnia tabelę participant_download_tokens dla istniejących uczestników z e-mailem (jednorazowy backfill)';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Tryb dry-run – żadne zmiany nie będą zapisane.');
        }

        $this->info('Pobieranie unikalnych e-maili z participants (batch po ' . $batchSize . ')...');

        $processed = 0;
        $created = 0;
        $alreadyExists = 0;

        Participant::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select(['id', 'email'])
            ->chunkById($batchSize, function ($participants) use ($dryRun, &$processed, &$created, &$alreadyExists) {
                $emails = $participants->pluck('email')->unique()->filter();
                foreach ($emails as $email) {
                    $normalized = ParticipantDownloadToken::normalizeEmail($email);
                    if ($normalized === '') {
                        continue;
                    }
                    $processed++;

                    if ($dryRun) {
                        $exists = ParticipantDownloadToken::where('email_normalized', $normalized)->exists();
                        if ($exists) {
                            $alreadyExists++;
                        } else {
                            $created++;
                        }
                        continue;
                    }

                    $record = ParticipantDownloadToken::firstOrCreate(
                        ['email_normalized' => $normalized],
                        ['token' => \Illuminate\Support\Str::random(64)]
                    );
                    if ($record->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $alreadyExists++;
                    }
                }
            });

        $this->info("Zakończono. Przetworzono unikalnych e-maili: {$processed}, utworzono tokenów: {$created}, już istniało: {$alreadyExists}.");
        if ($dryRun) {
            $this->warn('Uruchom bez --dry-run, aby zapisać zmiany.');
        }

        return self::SUCCESS;
    }
}
