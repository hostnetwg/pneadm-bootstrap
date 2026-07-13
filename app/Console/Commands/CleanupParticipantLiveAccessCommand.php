<?php

namespace App\Console\Commands;

use App\Services\ParticipantLiveAccessService;
use Illuminate\Console\Command;

class CleanupParticipantLiveAccessCommand extends Command
{
    protected $signature = 'participants:cleanup-live-access
                            {--dry-run : Pokaż liczbę rekordów do usunięcia bez kasowania}';

    protected $description = 'Usuwa rekordy participant_live_access po zakończeniu szkolenia (expires_at w przeszłości)';

    public function handle(ParticipantLiveAccessService $service): int
    {
        if ($this->option('dry-run')) {
            $count = \App\Models\ParticipantLiveAccess::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->count();
            $this->info("Do usunięcia: {$count} rekordów participant_live_access.");

            return self::SUCCESS;
        }

        $deleted = $service->cleanupExpiredRecords();
        $this->info("Usunięto {$deleted} rekordów participant_live_access.");

        return self::SUCCESS;
    }
}
