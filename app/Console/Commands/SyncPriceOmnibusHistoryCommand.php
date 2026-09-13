<?php

namespace App\Console\Commands;

use App\Services\PriceOmnibusService;
use Illuminate\Console\Command;

class SyncPriceOmnibusHistoryCommand extends Command
{
    protected $signature = 'omnibus:sync-prices';

    protected $description = 'Domknij historię cen, gdy promocja czasowa zaczęła się albo skończyła bez zapisu w ADM';

    public function handle(PriceOmnibusService $omnibus): int
    {
        $count = $omnibus->syncAll();
        $this->info("Zsynchronizowano historię cen dla {$count} wariantów.");

        return self::SUCCESS;
    }
}
