<?php

namespace App\Console\Commands;

use App\Models\GrowthOS\GrowthCampaign;
use App\Services\GrowthOS\GrowthOperationalTasks;
use Illuminate\Console\Command;

class SeedGrowthOperationalTasksCommand extends Command
{
    protected $signature = 'growth:seed-operational-tasks';

    protected $description = 'Jednorazowo uzupełnia 9 zadań operacyjnych dla istniejących kampanii Growth OS';

    public function handle(GrowthOperationalTasks $tasks): int
    {
        $campaigns = 0;

        GrowthCampaign::query()->orderBy('id')->each(function (GrowthCampaign $campaign) use ($tasks, &$campaigns): void {
            $tasks->ensureForCampaign($campaign);
            $campaigns++;
        });

        $this->info('Zainicjowano zadania operacyjne dla '.$campaigns.' kampanii.');

        return self::SUCCESS;
    }
}
